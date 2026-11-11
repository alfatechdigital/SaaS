<?php

use App\Enums\Feature;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Plan
    |--------------------------------------------------------------------------
    |
    | Plan used when a tenant has no subscription yet (and as the trial plan).
    | Must be one of the keys below — validated by tests/Unit/PlansConfigTest.
    |
    */

    'default_plan' => 'basic',

    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    |
    | Feature lists are re-stated per plan on purpose (OQ-F4-2, 2026-10-28): no
    | inheritance, no "everything from the tier below". Reading one plan gives
    | its full contents, and a price or contract change cannot silently promote
    | features to another tier.
    |
    | Values must be `App\Enums\Feature` values. Nothing in the domain may ever
    | branch on the plan name — ask the entitlement service instead
    | (`$team->can(Feature::X)`); see phase-04 §4.3.5.
    |
    | Prices and billing metadata are not here on purpose: SaaS billing is a
    | separate workstream (B-2) and owns them.
    |
    */

    'plans' => [

        'basic' => [
            'name' => 'Basic',
            'features' => [
                Feature::CompanyProfile->value,
                Feature::TemplateSelection->value,
                Feature::Subdomain->value,
                Feature::SeoBasic->value,
                Feature::GoogleAnalytics->value,
                Feature::FileUpload->value,
                Feature::ContactForm->value,
            ],
            'limits' => [
                'users' => 1,
                'ai_generations_per_month' => 0,
                'whatsapp_messages_per_month' => 0,
            ],
        ],

        'plus' => [
            'name' => 'Plus',
            'features' => [
                Feature::CompanyProfile->value,
                Feature::TemplateSelection->value,
                Feature::Subdomain->value,
                Feature::SeoBasic->value,
                Feature::GoogleAnalytics->value,
                Feature::FileUpload->value,
                Feature::ContactForm->value,
                Feature::CustomDomain->value,
                Feature::WhatsAppBot->value,
                Feature::PaymentGateway->value,
                Feature::FormBuilder->value,
                Feature::EmailAutomation->value,
                Feature::GoogleSheetSync->value,
                Feature::LeadManagement->value,
            ],
            'limits' => [
                'users' => 3,
                'ai_generations_per_month' => 0,
                'whatsapp_messages_per_month' => 1000,
            ],
        ],

        'pro' => [
            'name' => 'Pro',
            'features' => [
                Feature::CompanyProfile->value,
                Feature::TemplateSelection->value,
                Feature::Subdomain->value,
                Feature::SeoBasic->value,
                Feature::GoogleAnalytics->value,
                Feature::FileUpload->value,
                Feature::ContactForm->value,
                Feature::CustomDomain->value,
                Feature::WhatsAppBot->value,
                Feature::PaymentGateway->value,
                Feature::FormBuilder->value,
                Feature::EmailAutomation->value,
                Feature::GoogleSheetSync->value,
                Feature::LeadManagement->value,
                Feature::ProjectManagement->value,
                Feature::TaskManagement->value,
                Feature::ContentPlanner->value,
                Feature::Finance->value,
                Feature::SeoAdvanced->value,
                Feature::ContentAi->value,
                Feature::AdvancedAutomation->value,
                Feature::RoleManagement->value,
            ],
            'limits' => [
                'users' => 10,
                'ai_generations_per_month' => 50,
                'whatsapp_messages_per_month' => 5000,
            ],
        ],

    ],

];
