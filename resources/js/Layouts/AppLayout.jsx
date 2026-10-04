import { Head, Link, usePage } from '@inertiajs/react';
import { Bell, Contact, FileText, LayoutDashboard, LogOut, Menu, Package, Receipt, Settings, Users, X } from 'lucide-react';
import PropTypes from 'prop-types';
import { useState } from 'react';
import { Avatar } from '@/Components/ui/Card';
import { Toaster } from '@/Components/ui/Toaster';
import { cn } from '@/lib/format';
import { useT } from '@/lib/i18n';

/**
 * Sidebar items. Each build phase adds its own (quotes, invoices, clients, items, settings).
 * `match` is the URL prefix that marks the item as active; `can` hides it without that permission.
 */
const NAV = [
    { key: 'dashboard', href: '/', match: '/', icon: LayoutDashboard },
    { key: 'quotes', href: '/quotes', match: '/quotes', icon: FileText },
    { key: 'invoices', href: '/invoices', match: '/invoices', icon: Receipt },
    { key: 'clients', href: '/clients', match: '/clients', icon: Contact },
    { key: 'items', href: '/items', match: '/items', icon: Package },
    { key: 'team', href: '/team', match: '/team', icon: Users, can: 'manage_team' },
    { key: 'settings', href: '/settings/company', match: '/settings', icon: Settings, can: 'manage_settings' },
];

/** The team's workspace: sidebar on desktop, slide-in menu on phones. */
export default function AppLayout({ title, actions, children }) {
    const t = useT();
    const { company, auth } = usePage().props;
    const [menuOpen, setMenuOpen] = useState(false);

    const sidebar = (
        <nav className="flex flex-1 flex-col gap-1 p-3">
            {NAV.filter((item) => !item.can || auth.can?.[item.can]).map(({ key, href, match, icon: Icon }) => (
                <NavLink key={key} href={href} match={match} onClick={() => setMenuOpen(false)}>
                    <Icon className="size-5" />
                    {t(`nav.${key}`)}
                </NavLink>
            ))}
        </nav>
    );

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen lg:flex">
                {/* Desktop sidebar */}
                <aside className="border-line bg-card hidden w-64 shrink-0 flex-col border-e lg:flex">
                    <Brand name={company?.name} />
                    {sidebar}
                    {auth.user && <UserBox user={auth.user} />}
                </aside>

                {/* Phone menu */}
                {menuOpen && (
                    <div className="fixed inset-0 z-40 lg:hidden">
                        <button type="button" className="bg-ink/40 absolute inset-0" onClick={() => setMenuOpen(false)} aria-label={t('common.close')} />
                        <aside className="bg-card absolute inset-y-0 start-0 flex w-72 max-w-[85%] flex-col shadow-xl">
                            <div className="flex items-center justify-between pe-3">
                                <Brand name={company?.name} />
                                <button
                                    type="button"
                                    onClick={() => setMenuOpen(false)}
                                    className="text-ink-muted hover:bg-surface rounded-lg p-2"
                                    aria-label={t('common.close')}
                                >
                                    <X className="size-5" />
                                </button>
                            </div>
                            {sidebar}
                            {auth.user && <UserBox user={auth.user} />}
                        </aside>
                    </div>
                )}

                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="border-line bg-card/90 sticky top-0 z-30 flex h-16 items-center gap-3 border-b px-4 backdrop-blur sm:px-6">
                        <button
                            type="button"
                            onClick={() => setMenuOpen(true)}
                            className="text-ink-muted hover:bg-surface rounded-lg p-2 lg:hidden"
                            aria-label={t('nav.menu')}
                        >
                            <Menu className="size-5" />
                        </button>
                        <h1 className="text-ink min-w-0 flex-1 truncate text-lg font-bold">{title}</h1>
                        {actions}
                        <NotificationBell count={auth.unread_notifications ?? 0} />
                    </header>
                    <main className="flex-1 p-4 sm:p-6">{children}</main>
                </div>
            </div>
            <Toaster />
        </>
    );
}

AppLayout.propTypes = { title: PropTypes.string.isRequired, actions: PropTypes.node, children: PropTypes.node };

function Brand({ name }) {
    return (
        <Link href="/" className="text-ink flex h-16 items-center gap-2 px-5 text-lg font-bold">
            <span className="bg-brand-600 inline-flex size-8 items-center justify-center rounded-lg text-white">و</span>
            <span className="truncate">{name ?? 'وافِق'}</span>
        </Link>
    );
}

Brand.propTypes = { name: PropTypes.string };

function NavLink({ href, match, onClick, children }) {
    const { url } = usePage();
    const path = url.split('?')[0];
    const active = match === '/' ? path === '/' : path.startsWith(match);

    return (
        <Link
            href={href}
            onClick={onClick}
            aria-current={active ? 'page' : undefined}
            className={cn(
                'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors',
                active ? 'bg-brand-50 text-brand-700' : 'text-ink-muted hover:bg-surface hover:text-ink',
            )}
        >
            {children}
        </Link>
    );
}

NavLink.propTypes = { href: PropTypes.string.isRequired, match: PropTypes.string.isRequired, onClick: PropTypes.func, children: PropTypes.node };

function UserBox({ user }) {
    const t = useT();

    return (
        <div className="border-line flex items-center gap-3 border-t p-4">
            <Avatar src={user.avatar} name={user.name} size={36} />
            <div className="min-w-0 flex-1">
                <p className="text-ink truncate text-sm font-semibold">{user.name}</p>
                <p className="text-ink-subtle truncate text-xs" dir="ltr">
                    {user.email}
                </p>
            </div>
            <Link
                href="/logout"
                method="post"
                as="button"
                className="text-ink-subtle hover:bg-surface hover:text-ink rounded-lg p-2"
                aria-label={t('nav.logout')}
                title={t('nav.logout')}
            >
                <LogOut className="size-4 rtl:-scale-x-100" />
            </Link>
        </div>
    );
}

UserBox.propTypes = { user: PropTypes.shape({ name: PropTypes.string, email: PropTypes.string, avatar: PropTypes.string }).isRequired };

/** Unread in-app notifications (for now: @mentions in comments). */
function NotificationBell({ count }) {
    const t = useT();

    return (
        <Link
            href="/notifications"
            className="text-ink-muted hover:bg-surface hover:text-ink relative rounded-lg p-2"
            aria-label={t('notifications.bell', { count })}
            title={t('notifications.title')}
        >
            <Bell className="size-5" />
            {count > 0 && (
                <span className="bg-danger absolute -end-0.5 -top-0.5 flex min-w-4.5 items-center justify-center rounded-full px-1 text-[0.65rem] leading-4.5 font-bold text-white">
                    {count > 99 ? '99+' : count}
                </span>
            )}
        </Link>
    );
}

NotificationBell.propTypes = { count: PropTypes.number.isRequired };
