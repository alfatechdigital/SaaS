<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\CompanyProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $company_name
 * @property string|null $description
 * @property string|null $about
 * @property array<int, array<string, mixed>>|null $services
 * @property array<int, array<string, mixed>>|null $products
 * @property string|null $contact
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property array<string, string>|null $social_links
 * @property array<int, array<string, string>>|null $faq
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable([
    'team_id',
    'company_name',
    'description',
    'about',
    'services',
    'products',
    'contact',
    'email',
    'phone',
    'address',
    'social_links',
    'faq',
])]
class CompanyProfile extends Model
{
    /** @use HasFactory<CompanyProfileFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Fase 2 (tugas 2.2.1): company profiles are protected by the team global
     * scope. Delete this override once every tenant model is covered.
     */
    protected static function usesTeamScope(): bool
    {
        return true;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'services' => 'array',
            'products' => 'array',
            'social_links' => 'array',
            'faq' => 'array',
        ];
    }
}
