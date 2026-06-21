import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    LayoutGrid,
    ListChecks,
    PhoneCall,
    Trophy,
    Users,
    UsersRound,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes/backoffice';
import type { NavItem } from '@/types';

export function BackofficeSidebar() {
    const { auth } = usePage().props;
    const isAdmin = auth.roles.includes('admin');

    const navItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Follow-ups',
            href: '/backoffice/follow-ups',
            icon: ListChecks,
        },
        {
            title: 'Leads',
            href: '/backoffice/leads',
            icon: UsersRound,
        },
        {
            title: 'Call Logs',
            href: '/backoffice/call-logs',
            icon: PhoneCall,
        },
        {
            title: 'Reports',
            href: '/backoffice/reports/agent-performance',
            icon: BarChart3,
        },
        {
            title: 'Leaderboard',
            href: '/backoffice/leaderboard',
            icon: Trophy,
        },
    ];

    if (isAdmin) {
        navItems.push({
            title: 'Users',
            href: '/backoffice/users',
            icon: Users,
        });
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
