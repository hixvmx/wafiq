import { Link, usePage } from '@inertiajs/react';
import { Building2, FileText, Hash, Mail, MessageSquareText, Palette, Percent } from 'lucide-react';
import PropTypes from 'prop-types';
import { useEffect, useRef } from 'react';
import { Button } from '@/Components/ui/Button';
import { cn } from '@/lib/format';
import { useT } from '@/lib/i18n';
import AppLayout from './AppLayout';

const SECTIONS = [
    { key: 'company', icon: Building2 },
    { key: 'branding', icon: Palette },
    { key: 'taxes', icon: Percent },
    { key: 'numbering', icon: Hash },
    { key: 'documents', icon: FileText },
    { key: 'messages', icon: MessageSquareText },
    // In the SaaS the mail is ours, not the customer's.
    { key: 'mail', icon: Mail, selfHostedOnly: true },
];

/** Settings pages: section list beside the form (a scrollable tab bar on phones). */
export default function SettingsLayout({ children }) {
    const t = useT();
    const { url } = usePage();
    const { app } = usePage().props;
    const current = url.split('?')[0].split('/')[2];
    const nav = useRef(null);

    // On phones the sections are a scrolling tab bar: bring the open one into view.
    useEffect(() => {
        nav.current?.querySelector('[aria-current="page"]')?.scrollIntoView({ block: 'nearest', inline: 'center' });
    }, [current]);

    return (
        <AppLayout title={t('settings.title')}>
            <div className="mx-auto flex max-w-5xl flex-col gap-6 lg:flex-row">
                <nav ref={nav} className="-mx-4 flex shrink-0 gap-1 overflow-x-auto px-4 lg:mx-0 lg:w-56 lg:flex-col lg:px-0">
                    {SECTIONS.filter((section) => !section.selfHostedOnly || app.edition !== 'saas').map(({ key, icon: Icon }) => (
                        <Link
                            key={key}
                            href={`/settings/${key}`}
                            aria-current={current === key ? 'page' : undefined}
                            className={cn(
                                'flex shrink-0 items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-medium whitespace-nowrap transition-colors',
                                current === key ? 'bg-card text-brand-700 shadow-card ring-line ring-1' : 'text-ink-muted hover:bg-card hover:text-ink',
                            )}
                        >
                            <Icon className="size-4.5" />
                            {t(`settings.sections.${key}`)}
                        </Link>
                    ))}
                </nav>
                <div className="min-w-0 flex-1 space-y-6">{children}</div>
            </div>
        </AppLayout>
    );
}

SettingsLayout.propTypes = { children: PropTypes.node };

/** A titled card holding one settings form. */
export function SettingsCard({ title, intro, children, footer }) {
    return (
        <section className="rounded-card border-line bg-card shadow-card border">
            <div className="space-y-5 p-5 sm:p-6">
                {(title || intro) && (
                    <header>
                        {title && <h2 className="text-ink text-base font-bold">{title}</h2>}
                        {intro && <p className="text-ink-muted mt-1 text-sm">{intro}</p>}
                    </header>
                )}
                {children}
            </div>
            {footer && <div className="border-line flex justify-end gap-2 border-t px-5 py-4 sm:px-6">{footer}</div>}
        </section>
    );
}

SettingsCard.propTypes = { title: PropTypes.node, intro: PropTypes.node, children: PropTypes.node, footer: PropTypes.node };

/** The usual "Save changes" footer button. */
export function SaveButton({ processing }) {
    const t = useT();

    return (
        <Button type="submit" disabled={processing}>
            {processing ? t('common.saving') : t('common.save')}
        </Button>
    );
}

SaveButton.propTypes = { processing: PropTypes.bool };
