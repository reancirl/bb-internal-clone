import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem } from '@/types';

interface AppLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    headerAction?: React.ReactNode;
}

export default ({ children, breadcrumbs, headerAction, ...props }: AppLayoutProps) => (
    <AppLayoutTemplate breadcrumbs={breadcrumbs} headerAction={headerAction} {...props}>
        {children}
    </AppLayoutTemplate>
);
