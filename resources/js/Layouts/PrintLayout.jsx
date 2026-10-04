import { Head } from '@inertiajs/react';
import PropTypes from 'prop-types';

/** Browser print view ("Print → Save as PDF"): an A4-width white page, no app chrome. */
export default function PrintLayout({ title, children }) {
    return (
        <>
            <Head title={title}>
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <main className="mx-auto min-h-screen max-w-[210mm] bg-white p-[12mm] text-ink print:p-0">{children}</main>
        </>
    );
}

PrintLayout.propTypes = { title: PropTypes.string.isRequired, children: PropTypes.node };
