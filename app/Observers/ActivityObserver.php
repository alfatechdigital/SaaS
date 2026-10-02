<?php

namespace App\Observers;

use App\Enums\ActivityAction;
use App\Enums\ActivityEntityType;
use App\Models\ActivityLog;
use App\Models\CompanyProfile;
use App\Models\ContentItem;
use App\Models\Lead;
use App\Models\PortfolioItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Writes an activity log entry whenever an observed domain model changes.
 *
 * @see \docs\IMPLEMENTATION_PLAN.md ADR-10
 */
class ActivityObserver
{
    /**
     * Metadata for every observed model.
     *
     * @var array<class-string<Model>, array{type: ActivityEntityType, label: string, attribute: string}>
     */
    private const ENTITIES = [
        CompanyProfile::class => ['type' => ActivityEntityType::CompanyProfile, 'label' => 'Profil perusahaan', 'attribute' => 'company_name'],
        Project::class => ['type' => ActivityEntityType::Project, 'label' => 'Proyek', 'attribute' => 'name'],
        Task::class => ['type' => ActivityEntityType::Task, 'label' => 'Tugas', 'attribute' => 'title'],
        Lead::class => ['type' => ActivityEntityType::Lead, 'label' => 'Lead', 'attribute' => 'company_name'],
        ContentItem::class => ['type' => ActivityEntityType::Content, 'label' => 'Konten', 'attribute' => 'title'],
        Transaction::class => ['type' => ActivityEntityType::Transaction, 'label' => 'Transaksi', 'attribute' => 'description'],
        PortfolioItem::class => ['type' => ActivityEntityType::Portfolio, 'label' => 'Portfolio', 'attribute' => 'title'],
    ];

    /**
     * Handle the "created" event.
     */
    public function created(Model $model): void
    {
        $this->log($model, ActivityAction::Created);
    }

    /**
     * Handle the "updated" event.
     */
    public function updated(Model $model): void
    {
        $this->log($model, ActivityAction::Updated);
    }

    /**
     * Handle the "deleted" event.
     */
    public function deleted(Model $model): void
    {
        $this->log($model, ActivityAction::Deleted);
    }

    /**
     * Persist an activity log entry for the given model.
     */
    private function log(Model $model, ActivityAction $action): void
    {
        $meta = self::ENTITIES[$model::class] ?? null;

        $teamId = $model->getAttribute('team_id');

        if ($meta === null || $teamId === null) {
            return;
        }

        $name = (string) $model->getAttribute($meta['attribute']);
        $userId = Auth::id();

        ActivityLog::create([
            'team_id' => $teamId,
            'action' => sprintf('%s %s', $action->verb(), mb_strtolower($meta['label'])),
            'entity_type' => $meta['type'],
            'entity_id' => (string) $model->getKey(),
            'details' => sprintf('%s %s: %s', $meta['label'], $action->state(), $name),
            'performed_by_id' => $userId === null ? null : (int) $userId,
        ]);
    }
}
