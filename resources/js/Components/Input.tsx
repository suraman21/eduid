import React from 'react';

interface FieldWrapProps {
    label?: string;
    error?: string;
    required?: boolean;
    hint?: string;
    children: React.ReactNode;
    htmlFor?: string;
}

/** Label + error wrapper shared by all field types. Theme-neutral via currentColor. */
export function Field({ label, error, required, hint, children, htmlFor }: FieldWrapProps) {
    return (
        <div>
            {label && (
                <label htmlFor={htmlFor} className="block text-xs font-semibold uppercase tracking-wider mb-2 text-slate-500 dark:text-slate-300">
                    {label}{required && <span className="text-rose-500 ml-0.5">*</span>}
                </label>
            )}
            {children}
            {hint && !error && <p className="mt-1.5 text-xs text-slate-400">{hint}</p>}
            {error && <p className="mt-1.5 text-xs text-rose-500">{error}</p>}
        </div>
    );
}

const fieldClass =
    'w-full px-4 py-2.5 rounded-xl text-sm transition-all duration-150 ' +
    'bg-white border border-slate-200 text-slate-900 placeholder-slate-400 ' +
    'focus:ring-2 focus:ring-purple-500/40 focus:border-purple-400 focus:outline-none ' +
    'dark:bg-white/5 dark:border-white/10 dark:text-slate-100 dark:placeholder-slate-600';

type InputProps = React.InputHTMLAttributes<HTMLInputElement> & {
    label?: string; error?: string; hint?: string;
};

export default function Input({ label, error, hint, required, className = '', id, ...rest }: InputProps) {
    return (
        <Field label={label} error={error} hint={hint} required={required} htmlFor={id}>
            <input id={id} required={required} className={`${fieldClass} ${className}`} {...rest} />
        </Field>
    );
}

type TextareaProps = React.TextareaHTMLAttributes<HTMLTextAreaElement> & {
    label?: string; error?: string; hint?: string;
};

export function Textarea({ label, error, hint, required, className = '', id, ...rest }: TextareaProps) {
    return (
        <Field label={label} error={error} hint={hint} required={required} htmlFor={id}>
            <textarea id={id} required={required} className={`${fieldClass} ${className}`} {...rest} />
        </Field>
    );
}

type SelectProps = React.SelectHTMLAttributes<HTMLSelectElement> & {
    label?: string; error?: string; hint?: string; children: React.ReactNode;
};

export function Select({ label, error, hint, required, className = '', id, children, ...rest }: SelectProps) {
    return (
        <Field label={label} error={error} hint={hint} required={required} htmlFor={id}>
            <select id={id} required={required} className={`${fieldClass} ${className}`} {...rest}>
                {children}
            </select>
        </Field>
    );
}
