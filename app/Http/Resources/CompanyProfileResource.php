<?php

namespace App\Http\Resources;

use App\Models\CompanyProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CompanyProfile
 */
class CompanyProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'companyName' => $this->company_name,
            'description' => $this->description,
            'about' => $this->about,
            'services' => $this->services ?? [],
            'products' => $this->products ?? [],
            'contact' => $this->contact,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'socialLinks' => $this->social_links ?? [],
            'faq' => $this->faq ?? [],
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
