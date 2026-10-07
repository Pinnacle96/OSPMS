import { useEffect, useRef, useId, type ReactNode } from 'react';
import { X } from 'lucide-react';
export default function Modal({ open, title, onClose, children }: { open: boolean; title: string; onClose: () => void; children: ReactNode }) {
    const ref = useRef<HTMLDialogElement>(null);
    const titleId = useId();
    useEffect(() => { const dialog = ref.current; if (!dialog) return; if (open && !dialog.open) dialog.showModal(); if (!open && dialog.open) dialog.close(); }, [open]);
    return <dialog ref={ref} className="modal" onCancel={onClose} aria-labelledby={titleId}><div className="panel-header"><h2 id={titleId}>{title}</h2><button type="button" className="icon-button" aria-label="Close dialog" onClick={onClose}><X size={18} /></button></div><div className="panel-body">{children}</div></dialog>;
}
