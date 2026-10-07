import type { ReactNode } from 'react';
export default function FormField({ label, id, error, hint, children }: { label: string; id: string; error?: string; hint?: string; children: ReactNode }) {
    return <div className="form-field"><label htmlFor={id}>{label}</label>{children}{hint && <p className="field-hint" id={`${id}-hint`}>{hint}</p>}{error && <p className="field-error" id={`${id}-error`} role="alert">{error}</p>}</div>;
}
