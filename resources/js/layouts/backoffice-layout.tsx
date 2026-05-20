import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { BackofficeSidebar } from '@/components/backoffice-sidebar';
import { FlashToaster } from '@/components/flash-toaster';
import type { AppLayoutProps } from '@/types';

export default function BackofficeLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <BackofficeSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <main className="flex h-full flex-1 flex-col gap-6 p-4 md:px-12 md:py-6">
                    {children}
                </main>
            </AppContent>
            <FlashToaster />
        </AppShell>
    );
}
