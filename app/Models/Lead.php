<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\LeadStatus;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $company_name
 * @property string|null $contact_name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $source
 * @property string|null $potential_project
 * @property int $estimated_value
 * @property LeadStatus $status
 * @property string|null $next_follow_up
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable([
    'team_id',
    'company_name',
    'contact_name',
    'phone',
    'email',
    'source',
    'potential_project',
    'estimated_value',
    'status',
    'next_follow_up',
    'notes',
])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'estimated_value' => 'integer',
        ];
    }
}
