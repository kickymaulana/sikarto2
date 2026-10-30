import type { PageProps } from '@inertiajs/core';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    roles: string[];
    permissions: string[];
}

export interface SharedProps {
    auth: {
        user: AuthUser | null;
    } | null;
    flash: {
        success: string | null;
        error: string | null;
    } | null;
}

declare module '@inertiajs/core' {
    interface PageProps extends SharedProps {}
}
