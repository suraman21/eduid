export interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    school_id: number | null;
}

export interface School {
    name: string;
    code: string;
    accent_color: string | null;
    plan_tier: string;
}

export interface PageProps {
    auth: {
        user: User | null;
    };
    school: School | null;
    /** Flat, always-present accent colour (hex). Immune to page `school` shadowing. */
    accent: string;
    flash: {
        success: string | null;
        error: string | null;
    };
    errors: Record<string, string>;
    [key: string]: unknown;
}
