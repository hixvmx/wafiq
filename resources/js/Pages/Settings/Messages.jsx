import { useForm, usePage } from '@inertiajs/react';
import { MessageCircle, RotateCcw } from 'lucide-react';
import PropTypes from 'prop-types';
import { useRef } from 'react';
import { Button } from '@/Components/ui/Button';
import { Field, TextArea, TextInput } from '@/Components/ui/Field';
import SettingsLayout, { SaveButton, SettingsCard } from '@/Layouts/SettingsLayout';
import { useT } from '@/lib/i18n';

const TYPES = ['quote', 'invoice'];
const FIELDS = ['whatsapp', 'email_subject', 'email_body'];

/** Same rule as App\Support\MessageTemplate::render(): unknown variables stay as typed. */
function render(template, values) {
    return template.replace(/\{([a-z_]+)\}/g, (match, name) => values[name] ?? match);
}

export default function Messages({ templates, variables, defaults }) {
    const t = useT();
    const form = useForm(templates);

    const set = (type, key, value) => form.setData(type, { ...form.data[type], [key]: value });

    const submit = (e) => {
        e.preventDefault();
        form.put('/settings/messages', { preserveScroll: true });
    };

    return (
        <SettingsLayout>
            <form onSubmit={submit} className="space-y-6">
                {TYPES.map((type) => (
                    <SettingsCard
                        key={type}
                        title={t(`settings.types.${type}`)}
                        intro={type === 'quote' ? t('settings.messages.intro') : undefined}
                        footer={
                            <Button variant="ghost" size="sm" icon={<RotateCcw className="size-4" />} onClick={() => form.setData(type, defaults[type])}>
                                {t('settings.messages.reset')}
                            </Button>
                        }
                    >
                        {FIELDS.map((field) => (
                            <TemplateField
                                key={field}
                                field={field}
                                value={form.data[type][field]}
                                variables={variables[type]}
                                error={form.errors[`${type}.${field}`]}
                                onChange={(value) => set(type, field, value)}
                            />
                        ))}
                        <WhatsAppPreview text={form.data[type].whatsapp} />
                    </SettingsCard>
                ))}
                <div className="flex justify-end">
                    <SaveButton processing={form.processing} />
                </div>
            </form>
        </SettingsLayout>
    );
}

const templateShape = PropTypes.shape({ whatsapp: PropTypes.string, email_subject: PropTypes.string, email_body: PropTypes.string });

Messages.propTypes = {
    templates: PropTypes.shape({ quote: templateShape, invoice: templateShape }).isRequired,
    variables: PropTypes.shape({ quote: PropTypes.arrayOf(PropTypes.string), invoice: PropTypes.arrayOf(PropTypes.string) }).isRequired,
    defaults: PropTypes.shape({ quote: templateShape, invoice: templateShape }).isRequired,
};

/** A template input with clickable variable chips that insert at the cursor. */
function TemplateField({ field, value, variables, error, onChange }) {
    const t = useT();
    const ref = useRef(null);
    const multiline = field !== 'email_subject';

    const insert = (name) => {
        const el = ref.current;
        const token = `{${name}}`;
        const start = el?.selectionStart ?? value.length;
        const end = el?.selectionEnd ?? value.length;
        onChange(value.slice(0, start) + token + value.slice(end));
        requestAnimationFrame(() => {
            el?.focus();
            el?.setSelectionRange(start + token.length, start + token.length);
        });
    };

    const Control = multiline ? TextArea : TextInput;

    return (
        <Field label={t(`settings.messages.${field}`)} hint={field === 'email_body' ? t('settings.messages.email_body_hint') : undefined} error={error}>
            {(id, describedBy) => (
                <>
                    <Control ref={ref} id={id} value={value} onChange={(e) => onChange(e.target.value)} error={error} aria-describedby={describedBy} />
                    <div className="flex flex-wrap items-center gap-1.5 pt-1">
                        <span className="text-ink-subtle text-xs">{t('settings.messages.variables')}</span>
                        {variables.map((name) => (
                            <button
                                key={name}
                                type="button"
                                onClick={() => insert(name)}
                                className="bg-surface text-ink-muted ring-line hover:bg-brand-50 hover:text-brand-700 rounded-full px-2.5 py-1 text-xs font-medium ring-1"
                            >
                                {t(`settings.messages.variable_names.${name}`)}
                            </button>
                        ))}
                    </div>
                </>
            )}
        </Field>
    );
}

TemplateField.propTypes = {
    field: PropTypes.string.isRequired,
    value: PropTypes.string.isRequired,
    variables: PropTypes.arrayOf(PropTypes.string).isRequired,
    error: PropTypes.string,
    onChange: PropTypes.func.isRequired,
};

/** How the WhatsApp message will look, with sample values. */
function WhatsAppPreview({ text }) {
    const t = useT();
    const { company } = usePage().props;
    const sample = { ...samples(t), company: company?.name ?? '' };

    return (
        <div>
            <p className="text-ink mb-2 flex items-center gap-1.5 text-sm font-medium">
                <MessageCircle className="size-4 text-green-600" />
                {t('settings.messages.preview')}
            </p>
            <div className="rounded-xl bg-[#e5ddd5] p-4">
                <p className="text-ink max-w-md rounded-lg rounded-ss-none bg-[#dcf8c6] px-3 py-2 text-sm leading-relaxed whitespace-pre-line shadow-sm">
                    {render(text, sample)}
                </p>
            </div>
        </div>
    );
}

WhatsAppPreview.propTypes = { text: PropTypes.string.isRequired };

function samples(t) {
    return Object.fromEntries(
        ['client_name', 'number', 'amount', 'link', 'valid_until', 'due_date'].map((name) => [name, t(`settings.messages.sample.${name}`)]),
    );
}
