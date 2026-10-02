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
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureObservers();
        $this->configureResources();
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
