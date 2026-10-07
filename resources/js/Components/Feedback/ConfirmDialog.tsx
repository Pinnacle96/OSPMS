import Modal from './Modal';
export default function ConfirmDialog({ open, title, description, onConfirm, onClose }: { open: boolean; title: string; description: string; onConfirm: () => void; onClose: () => void }) {
    return <Modal open={open} title={title} onClose={onClose}><p className="muted small">{description}</p><div className="form-actions"><button type="button" className="button secondary" onClick={onClose}>Cancel</button><button type="button" className="button danger" onClick={onConfirm}>Confirm change</button></div></Modal>;
}
