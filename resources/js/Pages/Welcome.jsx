import { CheckCircle2 } from 'lucide-react';
import { Card } from '@/Components/ui/Card';
import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/lib/i18n';

/** Temporary home page until the dashboard (Phase 9) and login (Phase 1) exist. */
export default function Welcome() {
    const t = useT();

    return (
        <AppLayout title={t('nav.dashboard')}>
            <Card className="mx-auto mt-10 max-w-md p-8 text-center">
                <CheckCircle2 className="mx-auto size-12 text-brand-600" />
                <h2 className="mt-4 text-2xl font-bold">وافِق</h2>
                <p className="mt-2 text-ink-muted">{t('app.tagline')}</p>
            </Card>
        </AppLayout>
    );
}
