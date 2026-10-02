<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasTeams;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_team_id
 * @property string|null $job_title
 * @property string|null $phone
 * @property string|null $photo_path
 * @property array<int, string>|null $skills
 * @property bool $is_active
 * @property bool $is_platform_admin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $currentTeam
 * @property-read Collection<int, Team> $ownedTeams
 * @property-read Collection<int, Membership> $teamMemberships
 * @property-read Collection<int, Team> $teams
 * @property-read Collection<int, Project> $projectsAsPic
 * @property-read Collection<int, Task> $tasksAsAssignee
 * @property-read Collection<int, ContentItem> $contentItemsAsAssignee
 * @property-read Collection<int, Transaction> $transactions
 */
#[Fillable([
    'name',
    'email',
    'password',
    'current_team_id',
    'job_title',
    'phone',
    'photo_path',
    'skills',
    'is_active',
    'is_platform_admin',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasTeams, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'skills' => 'array',
            'is_active' => 'boolean',
            'is_platform_admin' => 'boolean',
        ];
    }

    /**
     * Determine whether this user operates the platform itself.
     *
     * Platform rights are cross-tenant and must never be derived from
     * `TeamRole` — see PDR-03 and PDR-05.
     */
    public function isPlatformAdmin(): bool
    {
        // Cast defensively: a freshly inserted model does not carry DB-level
        // column defaults until it is refreshed from the database.
        return (bool) $this->is_platform_admin;
    }

    /**
     * Get the projects this user is in charge of.
     *
     * @return HasMany<Project, $this>
     */
    public function projectsAsPic(): HasMany
    {
        return $this->hasMany(Project::class, 'pic_id');
    }

    /**
     * Get the tasks assigned to this user.
     *
     * @return HasMany<Task, $this>
     */
    public function tasksAsAssignee(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    /**
     * Get the content items assigned to this user.
     *
     * @return HasMany<ContentItem, $this>
     */
    public function contentItemsAsAssignee(): HasMany
    {
        return $this->hasMany(ContentItem::class, 'assignee_id');
    }

    /**
     * Get the transactions recorded by this user.
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'created_by_id');
    }
}
