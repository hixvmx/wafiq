import { Head, usePage } from '@inertiajs/react';
import PropTypes from 'prop-types';
import { Card } from '@/Components/ui/Card';
import { Toaster } from '@/Components/ui/Toaster';

/** Login, magic-link and invitation pages: a centred card under the company name. */
export default function AuthLayout({ title, subtitle, footer, children }) {
    const { company, app } = usePage().props;

    return (
        <>
            <Head title={title} />
            <main className="flex min-h-screen flex-col items-center justify-center px-4 py-10">
                <p className="mb-6 text-xl font-bold text-ink">{company?.name ?? app.name}</p>
                <Card className="w-full max-w-md p-6 sm:p-8">
                    <h1 className="text-2xl font-bold text-ink">{title}</h1>
                    {subtitle && <p className="mt-1 mb-6 text-sm text-ink-muted">{subtitle}</p>}
                    {children}
                </Card>
                {footer && <p className="mt-5 text-center text-sm text-ink-muted">{footer}</p>}
            </main>
            <Toaster />
        </>
    );
}

AuthLayout.propTypes = { title: PropTypes.string.isRequired, subtitle: PropTypes.node, footer: PropTypes.node, children: PropTypes.node };
