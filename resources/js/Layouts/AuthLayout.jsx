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
                <p className="text-ink mb-6 text-xl font-bold">{company?.name ?? app.name}</p>
                <Card className="w-full max-w-md p-6 sm:p-8">
                    <h1 className="text-ink text-2xl font-bold">{title}</h1>
                    {subtitle && <p className="text-ink-muted mt-1 mb-6 text-sm">{subtitle}</p>}
                    {children}
                </Card>
                {footer && <p className="text-ink-muted mt-5 text-center text-sm">{footer}</p>}
            </main>
            <Toaster />
        </>
    );
}

AuthLayout.propTypes = { title: PropTypes.string.isRequired, subtitle: PropTypes.node, footer: PropTypes.node, children: PropTypes.node };
