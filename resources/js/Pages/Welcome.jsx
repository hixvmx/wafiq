import { Head } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';

/** Temporary page proving the stack works (Laravel + Inertia + React + Tailwind, RTL Arabic). */
export default function Welcome() {
    return (
        <>
            <Head title="مرحباً" />
            <main className="flex min-h-screen items-center justify-center p-6">
                <div className="max-w-md rounded-2xl border border-line bg-white p-8 text-center shadow-sm">
                    <CheckCircle2 className="mx-auto size-12 text-brand-600" />
                    <h1 className="mt-4 text-2xl font-bold">وافِق</h1>
                    <p className="mt-2 text-ink-muted">أرسل عرض السعر أو الفاتورة، تابع فتحها، واحصل على موافقة عميلك بضغطة.</p>
                </div>
            </main>
        </>
    );
}
