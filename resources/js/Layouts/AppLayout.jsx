import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Bell, Contact, FileText, LayoutDashboard, LogOut, Menu, Package, Plus, Receipt, Settings, UserRound, Users, X } from 'lucide-react';
import PropTypes from 'prop-types';
import { useState } from 'react';
import { ButtonLink } from '@/Components/ui/Button';
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

/**
 * The team's workspace. On desktop the sidebar stays on screen while the page scrolls;
 * on phones it slides in from the menu button. The header sticks to the top of the page.
 *
 * `back` adds an arrow before the title (detail and form pages), `actions` sits at the end.
 */
export default function AppLayout({ title, back, actions, children }) {
    const t = useT();
    const { company, auth } = usePage().props;
    const [menuOpen, setMenuOpen] = useState(false);

    const sidebar = (
        <>
            {auth.can?.create_documents && (
                <div className="px-3 pt-4">
                    <ButtonLink href="/quotes/create" icon={<Plus className="size-4" />} className="w-full" onClick={() => setMenuOpen(false)}>
                        {t('documents.types.quote.new')}
                    </ButtonLink>
                </div>
            )}
            <nav className="flex flex-1 flex-col gap-1 overflow-y-auto p-3">
                {NAV.filter((item) => !item.can || auth.can?.[item.can]).map(({ key, href, match, icon: Icon }) => (
                    <NavLink key={key} href={href} match={match} onClick={() => setMenuOpen(false)}>
                        <Icon className="size-5 shrink-0" />
                        {t(`nav.${key}`)}
                    </NavLink>
                ))}
            </nav>
            {auth.user && <UserBox user={auth.user} />}
        </>
    );

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen lg:flex">
                {/* Desktop sidebar: fixed height, its own scroll, never moves with the page. */}
                <aside className="border-line bg-card sticky top-0 hidden h-screen w-64 shrink-0 flex-col border-e lg:flex">
                    <div className="border-line h-16 shrink-0 border-b">
                        <Brand name={company?.name} logo={company?.logo_url} />
                    </div>
                    {sidebar}
                </aside>

                {/* Phone menu */}
                {menuOpen && (
                    <div className="fixed inset-0 z-40 lg:hidden">
                        <button type="button" className="bg-ink/40 absolute inset-0" onClick={() => setMenuOpen(false)} aria-label={t('common.close')} />
                        <aside className="bg-card absolute inset-y-0 inset-s-0 flex w-72 max-w-[85%] flex-col shadow-xl">
                            <div className="border-line flex h-16 shrink-0 items-center justify-between border-b pe-3">
                                <Brand name={company?.name} logo={company?.logo_url} />
                                <button
                                    type="button"
                                    onClick={() => setMenuOpen(false)}
                                    className="text-ink-muted hover:bg-surface shrink-0 rounded-lg p-2"
                                    aria-label={t('common.close')}
                                >
                                    <X className="size-5" />
                                </button>
                            </div>
                            {sidebar}
                        </aside>
                    </div>
                )}

                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="border-line bg-card/95 sticky top-0 z-30 flex h-16 shrink-0 items-center gap-2 border-b px-3 backdrop-blur sm:gap-3 sm:px-6">
                        <button
                            type="button"
                            onClick={() => setMenuOpen(true)}
                            className="text-ink-muted hover:bg-surface rounded-lg p-2 lg:hidden"
                            aria-label={t('nav.menu')}
                        >
                            <Menu className="size-5" />
                        </button>
                        {back && (
                            <Link
                                href={back}
                                className="text-ink-muted hover:bg-surface hover:text-ink rounded-lg p-2"
                                aria-label={t('nav.back')}
                                title={t('nav.back')}
                            >
                                <ArrowRight className="size-5 ltr:-scale-x-100" />
                            </Link>
                        )}
                        <h1 className="text-ink min-w-0 flex-1 truncate text-base font-bold sm:text-lg">{title}</h1>
                        {actions && <div className="hidden shrink-0 items-center gap-2 sm:flex">{actions}</div>}
                        <NotificationBell count={auth.unread_notifications ?? 0} />
                        {auth.user && <UserMenu user={auth.user} />}
                    </header>
                    <main className="flex-1 p-4 sm:p-6">
                        {/* On phones the page actions get their own row, so the title keeps its room. */}
                        {actions && <div className="mb-4 flex flex-wrap items-center gap-2 sm:hidden">{actions}</div>}
                        {children}
                    </main>
                </div>
            </div>
            <Toaster />
        </>
    );
}

AppLayout.propTypes = { title: PropTypes.string.isRequired, back: PropTypes.string, actions: PropTypes.node, children: PropTypes.node };

function Brand({ name, logo }) {
    return (
        <Link href="/" className="text-ink flex h-full min-w-0 flex-1 items-center gap-2.5 px-4 font-bold" title={name}>
            {logo ? (
                <img src={logo} alt="" className="size-9 shrink-0 rounded-lg object-contain" />
            ) : (
                <span className="bg-brand-600 inline-flex size-9 shrink-0 items-center justify-center rounded-lg text-white">و</span>
            )}
            {/* Long company names wrap to two lines instead of being cut. */}
            <span className="line-clamp-2 text-sm leading-snug">{name ?? 'وافِق'}</span>
        </Link>
    );
}

Brand.propTypes = { name: PropTypes.string, logo: PropTypes.string };

/** Avatar menu in the header (profile, logout). Desktop shows the same in the sidebar, so it's for phones and tablets. */
function UserMenu({ user }) {
    const t = useT();
    const [open, setOpen] = useState(false);

    return (
        <div className="relative lg:hidden">
            <button type="button" onClick={() => setOpen(!open)} className="rounded-full p-0.5" aria-label={t('nav.profile')} aria-expanded={open}>
                <Avatar src={user.avatar} name={user.name} size={32} />
            </button>
            {open && (
                <>
                    <button type="button" className="fixed inset-0 z-40 cursor-default" onClick={() => setOpen(false)} aria-label={t('common.close')} />
                    <div className="border-line bg-card absolute inset-e-0 top-full z-50 mt-2 w-60 overflow-hidden rounded-xl border shadow-xl">
                        <div className="border-line border-b px-4 py-3">
                            <p className="text-ink truncate text-sm font-semibold">{user.name}</p>
                            <p className="text-ink-subtle truncate text-xs" dir="ltr">
                                {user.email}
                            </p>
                        </div>
                        <Link href="/profile" className="text-ink hover:bg-surface flex items-center gap-2 px-4 py-2.5 text-sm" onClick={() => setOpen(false)}>
                            <UserRound className="size-4" />
                            {t('nav.profile')}
                        </Link>
                        <Link href="/logout" method="post" as="button" className="text-ink hover:bg-surface flex w-full items-center gap-2 px-4 py-2.5 text-sm">
                            <LogOut className="size-4 rtl:-scale-x-100" />
                            {t('nav.logout')}
                        </Link>
                    </div>
                </>
            )}
        </div>
    );
}

UserMenu.propTypes = { user: PropTypes.shape({ name: PropTypes.string, email: PropTypes.string, avatar: PropTypes.string }).isRequired };

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
        <div className="border-line flex items-center gap-1 border-t p-3">
            <Link href="/profile" className="hover:bg-surface flex min-w-0 flex-1 items-center gap-3 rounded-xl p-1.5" title={t('nav.profile')}>
                <Avatar src={user.avatar} name={user.name} size={36} />
                <div className="min-w-0 flex-1">
                    <p className="text-ink truncate text-sm font-semibold">{user.name}</p>
                    <p className="text-ink-subtle truncate text-xs">{user.job_title || <span dir="ltr">{user.email}</span>}</p>
                </div>
            </Link>
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

UserBox.propTypes = {
    user: PropTypes.shape({ name: PropTypes.string, email: PropTypes.string, job_title: PropTypes.string, avatar: PropTypes.string }).isRequired,
};

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
                <span className="bg-danger absolute -inset-e-0.5 -top-0.5 flex min-w-4.5 items-center justify-center rounded-full px-1 text-[0.65rem] leading-4.5 font-bold text-white">
                    {count > 99 ? '99+' : count}
                </span>
            )}
        </Link>
    );
}

NotificationBell.propTypes = { count: PropTypes.number.isRequired };
