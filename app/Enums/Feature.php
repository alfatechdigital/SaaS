<?php

namespace App\Enums;

/**
 * Product features that a plan (or an add-on) can grant.
 *
 * Source of truth for the contents of each plan: docs/IMPLEMENTATION_PLAN.md
 * §B.4.1. The plan → feature mapping lives in `config/plans.php`.
 *
 * Adding a case here only registers the NAME of a feature. It does not mean the
 * feature exists, is usable, or may be advertised — implementation status is
 * tracked in §B.4.1 and in docs/implementation/phase-04-plan-dan-entitlement.md.
 * A case with no code behind it simply never blocks anything when checked.
 *
 * "SSL" and "prioritas support" from §B.4.1 are deliberately absent: both are
 * service commitments, not application capabilities, so nothing in the codebase
 * can (or should) gate them.
 */
enum Feature: string
{
    // Included in every plan (Basic and above).
    case CompanyProfile = 'company_profile';
    case TemplateSelection = 'template_selection';
    case Subdomain = 'subdomain';
    case SeoBasic = 'seo_basic';
    case GoogleAnalytics = 'google_analytics';
    case FileUpload = 'file_upload';
    case ContactForm = 'contact_form';

    // Plus and above.
    case CustomDomain = 'custom_domain';
    case WhatsAppBot = 'whatsapp_bot';
    case PaymentGateway = 'payment_gateway';
    case FormBuilder = 'form_builder';
    case EmailAutomation = 'email_automation';
    case GoogleSheetSync = 'google_sheet_sync';
    case LeadManagement = 'lead_management';

    // Pro only (also purchasable as add-ons — see §B.4.2).
    case ProjectManagement = 'project_management';
    case TaskManagement = 'task_management';
    case ContentPlanner = 'content_planner';
    case Finance = 'finance';
    case SeoAdvanced = 'seo_advanced';
    case ContentAi = 'content_ai';
    case AdvancedAutomation = 'advanced_automation';
    case RoleManagement = 'role_management';

    /**
     * Get the display label for the feature.
     */
    public function label(): string
    {
        return match ($this) {
            self::CompanyProfile => 'CMS (Profil & Portofolio)',
            self::TemplateSelection => 'Template Siap Pakai',
            self::Subdomain => 'Subdomain Gratis',
            self::SeoBasic => 'SEO Dasar',
            self::GoogleAnalytics => 'Google Analytics',
            self::FileUpload => 'Upload Gambar & File',
            self::ContactForm => 'Form Kontak Umum',
            self::CustomDomain => 'Custom Domain',
            self::WhatsAppBot => 'WhatsApp Bot',
            self::PaymentGateway => 'Payment Gateway',
            self::FormBuilder => 'Form Builder',
            self::EmailAutomation => 'Email Automation',
            self::GoogleSheetSync => 'Google Sheet Sync',
            self::LeadManagement => 'Lead Management',
            self::ProjectManagement => 'Manajemen Proyek',
            self::TaskManagement => 'Manajemen Tugas',
            self::ContentPlanner => 'Content Planner',
            self::Finance => 'Keuangan',
            self::SeoAdvanced => 'SEO Lanjutan',
            self::ContentAi => 'Content AI',
            self::AdvancedAutomation => 'Automation Lanjutan',
            self::RoleManagement => 'Role Management',
        };
    }

    /**
     * Get all the feature values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
