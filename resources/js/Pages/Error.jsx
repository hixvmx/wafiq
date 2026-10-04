import { Head, Link, usePage } from '@inertiajs/react';
import PropTypes from 'prop-types';
import { useT } from '@/lib/i18n';

// Fallbacks for when shared props are missing (e.g. a 404 outside any route).
const fallback = {
    403: ['غير مسموح', 'ليست لديك صلاحية للوصول إلى هذه الصفحة.'],
    404: ['الصفحة غير موجودة', 'ربما تم حذف هذه الصفحة أو تغيير رابطها.'],
    429: ['محاولات كثيرة', 'انتظر قليلاً ثم حاول مجدداً.'],
    500: ['حدث خطأ غير متوقع', 'يرجى المحاولة لاحقاً.'],
    503: ['النظام تحت الصيانة', 'سنعود قريباً.'],
};

/** Error page for Inertia visits (full page loads use resources/views/errors/*). */
export default function Error({ status }) {
    const t = useT();
    const app = usePage().props.app;
    const [title, message] = fallback[status] ?? fallback[500];

    return (
        <>
            <Head title={`${t(`errors.${status}.title`, {}, title)}${app ? ` - ${app.name}` : ''}`} />
            <main className="flex min-h-screen items-center justify-center px-4 text-center">
                <div className="max-w-md">
                    <p className="text-7xl font-bold text-brand-600">{status}</p>
                    <h1 className="mt-4 text-2xl font-bold text-ink">{t(`errors.${status}.title`, {}, title)}</h1>
                    <p className="mt-2 text-ink-muted">{t(`errors.${status}.message`, {}, message)}</p>
                    <Link href="/" className="mt-8 inline-flex h-11 items-center rounded-xl bg-brand-600 px-6 font-semibold text-white hover:bg-brand-700">
                        {t('errors.back_home', {}, 'العودة إلى الرئيسية')}
                    </Link>
                </div>
            </main>
        </>
    );
}

Error.propTypes = { status: PropTypes.number.isRequired };
