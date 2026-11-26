/**
 * Shapes shared by the tenant's "Paket saya" page and the platform panel.
 *
 * Written once because both sides read the same services ({@see Entitlements},
 * `SeatQuota`, `MonthlyQuota`); a second copy would drift the moment a prop is
 * added on one page only.
 */

export type PlanFeature = {
    value: string;
    label: string;
};

export type PlanSummary = {
    code: string;
    name: string;
    description: string | null;
    priceMonthly: number | null;
    priceYearly: number | null;
    features: PlanFeature[];
    limits: Record<string, number>;
};

export type SubscriptionSummary = {
    status: string;
    statusLabel: string;
    interval: string | null;
    trialEndsAt: string | null;
    periodStart: string | null;
    periodEnd: string | null;
    canceledAt: string | null;
};

export type UsageSummary = {
    users: { used: number; limit: number };
    ai: { used: number; limit: number; period: string };
};

export type InvoiceRow = {
    id: number;
    number: string;
    status: string;
    statusLabel: string;
    amount: number;
    periodStart: string;
    periodEnd: string;
    dueAt: string;
    paidAt: string | null;
    isOpen: boolean;
    hasPaymentLink: boolean;
};

export type AddonRow = {
    feature: string;
    label: string;
    startsAt: string | null;
    endsAt: string | null;
    active: boolean;
};

export type PlatformPlanRow = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    priceMonthly: number;
    priceYearly: number | null;
    isActive: boolean;
    isDefault: boolean;
    features: PlanFeature[];
    limits: Record<string, number>;
};
