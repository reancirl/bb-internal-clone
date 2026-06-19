export type Role = 'admin' | 'crew';

export interface Employee {
    id: number;
    name: string;
    email: string;
    role: Role;
    active: boolean;
    created_at: string | null;
}

export const ROLE_LABEL: Record<Role, string> = {
    admin: 'Admin',
    crew: 'Crew',
};
