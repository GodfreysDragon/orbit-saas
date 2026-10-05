export type Role = {
    value: 'owner' | 'admin' | 'member' | 'viewer';
    label: string;
    description: string;
};

export type OrganizationSummary = {
    id: number;
    name: string;
    slug: string;
    personal: boolean;
};

export type BillingStatus =
    | 'free'
    | 'trialing'
    | 'active'
    | 'canceling'
    | 'past_due';

export type CurrentOrganization = OrganizationSummary & {
    role: Role | null;
    permissions: string[];
    plan: { key: string; name: string };
    billingStatus: BillingStatus;
    trialEndsAt: string | null;
};

export type Plan = {
    key: string;
    name: string;
    description: string;
    prices: { monthly: number; yearly: number };
    limits: { members: number | null; projects: number | null };
    features: string[];
    highlighted: boolean;
    purchasable: boolean;
};

export type BillingSummary = {
    plan: Plan;
    status: BillingStatus;
    subscribed: boolean;
    interval: 'monthly' | 'yearly' | null;
    trialEndsAt: string | null;
    endsAt: string | null;
    paymentMethod: { brand: string; lastFour: string } | null;
};

export type Usage = {
    used: number;
    limit: number | null;
};

export type ActivityItem = {
    id: number;
    event: string;
    description: string;
    causer: { id: number; name: string } | null;
    properties: Record<string, unknown> | null;
    createdAt: string;
};

export type Project = {
    id: number;
    name: string;
    description: string | null;
    status: 'active' | 'paused' | 'archived';
    creator: string | null;
    createdAt: string;
    updatedAt?: string;
};

export type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
};
