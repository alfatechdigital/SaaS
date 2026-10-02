/**
 * Types for the Alfatech domain, ported from the React template's `types/index.ts`.
 *
 * These mirror the payloads produced by `app/Http/Resources/*Resource.php`:
 * camelCase keys, `id` as a number (auto-increment), and extra `*Label` fields
 * supplied by the PHP enums.
 *
 * @see docs/IMPLEMENTATION_PLAN.md ADR-05, ADR-14
 */

/* ------------------------------------------------------------------ Enums */

export type ProjectStatus =
    | 'lead'
    | 'negotiation'
    | 'deal'
    | 'development'
    | 'review'
    | 'completed'
    | 'cancelled';

export type TaskStatus = 'todo' | 'in_progress' | 'review' | 'done';
export type TaskPriority = 'low' | 'medium' | 'high';

export type LeadStatus =
    | 'new'
    | 'contacted'
    | 'follow_up'
    | 'meeting'
    | 'proposal'
    | 'negotiation'
    | 'won'
    | 'lost';

export type ContentStatus =
    | 'idea'
    | 'draft'
    | 'review'
    | 'approved'
    | 'scheduled'
    | 'published';

export type ContentPlatform = 'instagram' | 'facebook' | 'tiktok' | 'linkedin';

export type TransactionType = 'income' | 'expense';

export type TransactionCategory =
    | 'project_income'
    | 'project_expense'
    | 'company_expense'
    | 'capital'
    | 'other';

export type ActivityEntityType =
    | 'project'
    | 'lead'
    | 'content'
    | 'transaction'
    | 'task'
    | 'portfolio'
    | 'company_profile'
    | 'team';

/* -------------------------------------------------------------- Projects */

export type Project = {
    id: number;
    /** Set when this project was converted from a won lead. */
    leadId: number | null;
    name: string;
    clientName: string | null;
    description: string | null;
    status: ProjectStatus;
    statusLabel: string;
    progress: number;
    startDate: string | null;
    deadline: string | null;
    projectValue: number;
    picId: number | null;
    picName?: string | null;
    picRole?: string | null;
    technologies: string[];
    notes: string | null;
    createdAt: string | null;
    updatedAt: string | null;
};

export type Task = {
    id: number;
    projectId: number;
    title: string;
    description: string | null;
    assigneeId: number | null;
    assigneeName?: string | null;
    priority: TaskPriority;
    priorityLabel: string;
    status: TaskStatus;
    statusLabel: string;
    dueDate: string | null;
    createdAt: string | null;
    updatedAt: string | null;
};

/* ----------------------------------------------------------------- Leads */

export type Lead = {
    id: number;
    companyName: string;
    contactName: string | null;
    phone: string | null;
    email: string | null;
    source: string | null;
    potentialProject: string | null;
    estimatedValue: number;
    status: LeadStatus;
    statusLabel: string;
    nextFollowUp: string | null;
    notes: string | null;
    createdAt: string | null;
    updatedAt: string | null;
};

/* ------------------------------------------------------ Company profile */

export type CompanyService = {
    id: string;
    name: string;
    desc: string;
    icon: string;
};

export type CompanyProduct = {
    id: string;
    name: string;
    desc: string;
};

export type CompanyFaq = {
    question: string;
    answer: string;
};

export type CompanySocialLinks = {
    website?: string;
    instagram?: string;
    linkedin?: string;
    github?: string;
};

export type CompanyProfile = {
    id: number;
    companyName: string;
    description: string | null;
    about: string | null;
    services: CompanyService[];
    products: CompanyProduct[];
    contact: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    socialLinks: CompanySocialLinks;
    faq: CompanyFaq[];
    updatedAt: string | null;
};

/* ------------------------------------------------------------- Portfolio */

export type PortfolioItem = {
    id: number;
    title: string;
    client: string | null;
    category: string | null;
    description: string | null;
    technologies: string[];
    imageUrl: string | null;
    projectUrl: string | null;
    completionDate: string | null;
    featured: boolean;
    published: boolean;
    createdAt: string | null;
    updatedAt: string | null;
};

/* --------------------------------------------------------------- Content */

export type ContentItem = {
    id: number;
    title: string;
    caption: string | null;
    platform: ContentPlatform;
    platformLabel: string;
    contentType: string | null;
    mediaUrl: string | null;
    status: ContentStatus;
    statusLabel: string;
    scheduledAt: string | null;
    assigneeId: number | null;
    assigneeName?: string | null;
    notes: string | null;
    createdAt: string | null;
    updatedAt: string | null;
};

/* ----------------------------------------------------------- Transactions */

export type Transaction = {
    id: number;
    type: TransactionType;
    typeLabel: string;
    category: TransactionCategory;
    categoryLabel: string;
    projectId: number | null;
    projectName?: string | null;
    description: string;
    amount: number;
    date: string;
    createdById: number | null;
    createdByName?: string | null;
    createdAt: string | null;
};

/* ---------------------------------------------------------- Activity log */

export type ActivityLog = {
    id: number;
    action: string;
    /** `created` | `updated` | `deleted`, or null when unclassifiable. */
    actionType: 'created' | 'updated' | 'deleted' | null;
    entityType: ActivityEntityType;
    entityTypeLabel: string;
    entityId: string | null;
    details: string | null;
    performedById: number | null;
    performedByName?: string | null;
    createdAt: string | null;
};

/* --------------------------------------------------------------- Shared */

/**
 * A team member option as produced by `App\Support\TeamMembers`.
 */
export type MemberOption = {
    id: number;
    name: string;
    jobTitle: string | null;
    phone: string | null;
};

/**
 * A minimal project option, used by the finance ledger's project picker and its
 * per-project profitability table.
 */
export type ProjectOption = {
    id: number;
    name: string;
    clientName: string | null;
    projectValue: number;
};
