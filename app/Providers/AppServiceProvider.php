<?php

namespace App\Providers;

use App\Contracts\TeamEntitlementSource;
use App\Contracts\WhatsAppDriver;
use App\Models\CompanyProfile;
use App\Models\ContentItem;
use App\Models\Lead;
use App\Models\PortfolioItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\Transaction;
use App\Observers\ActivityObserver;
use App\Observers\PublicSeoObserver;
use App\Support\Entitlements;
use App\Support\NoSubscriptionSource;
use App\Support\TenantContext;
use App\Support\WhatsApp\LogDriver;
use App\Support\WhatsApp\MetaCloudDriver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One active tenant per request/job, shared by every collaborator.
        $this->app->singleton(TenantContext::class);

        // Entitlements read purchased plans/add-ons through a source. Nobody
        // subscribes yet, so nothing is purchased and every tenant runs on the
        // default plan; the billing workstream swaps this binding.
        $this->app->singleton(TeamEntitlementSource::class, NoSubscriptionSource::class);
        $this->app->singleton(Entitlements::class);

        // One WhatsApp transport per deployment. `log` by default, so nothing can
        // reach a real number unless WHATSAPP_DRIVER says so.
        $this->app->singleton(WhatsAppDriver::class, function (): WhatsAppDriver {
            $driver = (string) config('whatsapp.driver', 'log');

            return match ($driver) {
                'meta' => new MetaCloudDriver,
                'log' => new LogDriver,
                default => throw new InvalidArgumentException(
                    sprintf('Unsupported WhatsApp driver [%s]. Use "meta" or "log".', $driver),
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureObservers();
        $this->configureResources();
        $this->configureTenantContext();
    }

    /**
     * Clear the active tenant whenever a queued job starts.
     *
     * A queue worker boots the application once and then runs many jobs, so
     * without this the tenant activated by job N-1 would still be active for job
     * N. Jobs that work for a tenant must opt in explicitly with
     * {@see TenantContext::runFor()}.
     *
     * The `sync` driver is exempt: it does not run in a worker but inline, inside
     * the request or command that dispatched the job — the same process that set
     * the tenant on purpose. Clearing it there would not protect anything, and
     * would break the rest of that request (every tenant-scoped query after the
     * dispatch would fail loudly).
     *
     * @see \docs\implementation\phase-02-tenant-context.md tugas 2.1.4
     */
    protected function configureTenantContext(): void
    {
        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            if ($this->connectionRunsInline($event->connectionName)) {
                return;
            }

            app(TenantContext::class)->forget();
        });
    }

    /**
     * Whether the named queue connection executes jobs in the current process.
     */
    protected function connectionRunsInline(?string $connectionName): bool
    {
        if ($connectionName === null) {
            return false;
        }

        return config("queue.connections.{$connectionName}.driver") === 'sync';
    }

    /**
     * Drop the `data` wrapper from JSON resources.
     *
     * Resources are only ever returned nested inside an Inertia prop array. With
     * the default wrapper, `'items' => ItemResource::collection($items)` serialises
     * to `{ data: [...] }`, so the frontend receives an object instead of an array.
     *
     * @see \docs\IMPLEMENTATION_PLAN.md §12 temuan Fase 4
     */
    protected function configureResources(): void
    {
        JsonResource::withoutWrapping();
    }

    /**
     * Register the activity log observer on every domain model.
     *
     * @see \docs\IMPLEMENTATION_PLAN.md ADR-10
     */
    protected function configureObservers(): void
    {
        $models = [
            CompanyProfile::class,
            Project::class,
            Task::class,
            Lead::class,
            ContentItem::class,
            Transaction::class,
            PortfolioItem::class,
        ];

        foreach ($models as $model) {
            $model::observe(ActivityObserver::class);
        }

        // Sitemap pings follow public changes only (B-8) — the profile and the
        // portfolio feed the page and its sitemap; projects, tasks, leads, content,
        // and transactions do not. The tenant itself is observed too, because a
        // rename moves the canonical URL and a moderation/suspension decision
        // changes whether the page may be indexed at all.
        $publicModels = [
            CompanyProfile::class,
            PortfolioItem::class,
            Team::class,
        ];

        foreach ($publicModels as $model) {
            $model::observe(PublicSeoObserver::class);
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
