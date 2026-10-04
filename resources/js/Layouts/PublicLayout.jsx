import { Head } from '@inertiajs/react';
import PropTypes from 'prop-types';

/**
 * The client's page (no login): the company's logo and colour at the top, the document below.
 * `brand` comes from the document's company snapshot, not from the logged-in team.
 */
export default function PublicLayout({ title, brand, children }) {
    return (
        <>
            <Head title={title}>
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <div className="min-h-screen" style={brand?.color ? { '--color-brand-600': brand.color } : undefined}>
                <header className="border-b border-line bg-card">
                    <div className="container-page flex h-16 items-center gap-3">
                        {brand?.logo_url ? (
                            <img src={brand.logo_url} alt={brand.name} className="h-10 w-auto max-w-40 object-contain" />
                        ) : (
                            <span className="text-lg font-bold text-ink">{brand?.name}</span>
                        )}
                    </div>
                </header>
                <main className="container-page py-6 sm:py-10">{children}</main>
            </div>
        </>
    );
}

PublicLayout.propTypes = {
    title: PropTypes.string.isRequired,
    brand: PropTypes.shape({ name: PropTypes.string, logo_url: PropTypes.string, color: PropTypes.string }),
    children: PropTypes.node,
};
