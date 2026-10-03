<?php

namespace App\Providers;

use App\Models\CompanyProfile;
use App\Models\ContentItem;
use App\Models\Lead;
use App\Models\PortfolioItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\Transaction;
use App\Observers\ActivityObserver;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One active tenant per request/job, shared by every collaborator.
        $this->app->singleton(TenantContext::class);
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
     * @see \docs\implementation\phase-02-tenant-context.md tugas 2.1.4
     */
    protected function configureTenantContext(): void
    {
        Event::listen(JobProcessing::class, function (): void {
            app(TenantContext::class)->forget();
        });
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
