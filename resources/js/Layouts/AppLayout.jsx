import { Head, Link, usePage } from '@inertiajs/react';
import { LayoutDashboard, LogOut, Menu, Users, X } from 'lucide-react';
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
    { key: 'team', href: '/team', match: '/team', icon: Users, can: 'manage_team' },
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
                <aside className="hidden w-64 shrink-0 flex-col border-e border-line bg-card lg:flex">
                    <Brand name={company?.name} />
                    {sidebar}
                    {auth.user && <UserBox user={auth.user} />}
                </aside>

                {/* Phone menu */}
                {menuOpen && (
                    <div className="fixed inset-0 z-40 lg:hidden">
                        <button type="button" className="absolute inset-0 bg-ink/40" onClick={() => setMenuOpen(false)} aria-label={t('common.close')} />
                        <aside className="absolute inset-y-0 start-0 flex w-72 max-w-[85%] flex-col bg-card shadow-xl">
                            <div className="flex items-center justify-between pe-3">
                                <Brand name={company?.name} />
                                <button type="button" onClick={() => setMenuOpen(false)} className="rounded-lg p-2 text-ink-muted hover:bg-surface" aria-label={t('common.close')}>
                                    <X className="size-5" />
                                </button>
                            </div>
                            {sidebar}
                            {auth.user && <UserBox user={auth.user} />}
                        </aside>
                    </div>
                )}

                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-line bg-card/90 px-4 backdrop-blur sm:px-6">
                        <button type="button" onClick={() => setMenuOpen(true)} className="rounded-lg p-2 text-ink-muted hover:bg-surface lg:hidden" aria-label={t('nav.menu')}>
                            <Menu className="size-5" />
                        </button>
                        <h1 className="min-w-0 flex-1 truncate text-lg font-bold text-ink">{title}</h1>
                        {actions}
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
        <Link href="/" className="flex h-16 items-center gap-2 px-5 text-lg font-bold text-ink">
            <span className="inline-flex size-8 items-center justify-center rounded-lg bg-brand-600 text-white">و</span>
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
        <div className="flex items-center gap-3 border-t border-line p-4">
            <Avatar src={user.avatar} name={user.name} size={36} />
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold text-ink">{user.name}</p>
                <p className="truncate text-xs text-ink-subtle" dir="ltr">
                    {user.email}
                </p>
            </div>
            <Link
                href="/logout"
                method="post"
                as="button"
                className="rounded-lg p-2 text-ink-subtle hover:bg-surface hover:text-ink"
                aria-label={t('nav.logout')}
                title={t('nav.logout')}
            >
                <LogOut className="size-4 rtl:-scale-x-100" />
            </Link>
        </div>
    );
}

UserBox.propTypes = { user: PropTypes.shape({ name: PropTypes.string, email: PropTypes.string, avatar: PropTypes.string }).isRequired };
