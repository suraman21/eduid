import { AlertTriangle } from 'lucide-react';
import Modal from './Modal';
import Button from './Button';

interface Props {
    open: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title?: string;
    message: string;
    confirmLabel?: string;
    danger?: boolean;
    loading?: boolean;
}

export default function ConfirmDialog({
    open, onClose, onConfirm, title = 'Are you sure?', message,
    confirmLabel = 'Confirm', danger = true, loading,
}: Props) {
    return (
        <Modal open={open} onClose={onClose} maxWidth="sm">
            <div className="text-center">
                <div className={`mx-auto w-12 h-12 rounded-full flex items-center justify-center mb-4 ${danger ? 'bg-rose-500/15' : 'bg-amber-500/15'}`}>
                    <AlertTriangle className={`w-6 h-6 ${danger ? 'text-rose-500' : 'text-amber-500'}`} />
                </div>
                <h3 className="text-base font-semibold text-slate-900 dark:text-white">{title}</h3>
                <p className="mt-1.5 text-sm text-slate-500 dark:text-slate-400">{message}</p>
                <div className="mt-6 flex gap-3 justify-center">
                    <Button variant="secondary" onClick={onClose}>Cancel</Button>
                    <Button variant={danger ? 'danger' : 'primary'} onClick={onConfirm} loading={loading}>
                        {confirmLabel}
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
