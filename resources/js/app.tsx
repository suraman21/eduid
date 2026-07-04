import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import React from 'react';
import '../css/app.css';

/**
 * App-wide error boundary. A render-time exception in any page would otherwise
 * unmount everything and leave a blank screen. We catch it, show the user a
 * friendly message with a support reference and clear next actions, and send
 * the *technical* details to the SERVER log — never to the screen or the
 * browser console (except in local dev, to aid debugging).
 */
class ErrorBoundary extends React.Component<{ children: React.ReactNode }, { error: Error | null; ref: string }> {
    state = { error: null as Error | null, ref: '' };

    static getDerivedStateFromError(error: Error) {
        const ref = 'EDU-UI-' + Math.random().toString(16).slice(2, 10).toUpperCase();
        return { error, ref };
    }

    componentDidCatch(error: Error, info: { componentStack?: string }) {
        // Ship the real details to the server log, tagged with the same
        // reference the user sees. Fire-and-forget; never blocks the fallback.
        try {
            const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
            fetch('/client-error', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({
                    ref: this.state.ref,
                    message: error?.message ?? String(error),
                    stack: (info?.componentStack ?? error?.stack ?? '').slice(0, 5000),
                    url: window.location.href,
                }),
                keepalive: true,
            }).catch(() => {});
        } catch { /* logging must never itself throw */ }

        if (import.meta.env.DEV) console.error('Unhandled UI error:', error, info);
    }

    render() {
        if (this.state.error) {
            return (
                <div style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#0f172a', color: '#e2e8f0', fontFamily: 'system-ui, sans-serif', padding: 24 }}>
                    <div style={{ maxWidth: 460, textAlign: 'center' }}>
                        <div style={{ fontSize: 40, marginBottom: 12 }}>🛠️</div>
                        <h1 style={{ fontSize: 20, fontWeight: 800, marginBottom: 10, color: '#f8fafc' }}>Something went wrong on this page</h1>
                        <p style={{ fontSize: 14, lineHeight: 1.6, color: '#94a3b8', marginBottom: 18 }}>
                            We hit an unexpected problem and couldn’t finish loading this screen. It’s not your fault — our team has been notified automatically. Please try again.
                        </p>
                        <div style={{ fontFamily: 'ui-monospace, monospace', fontSize: 12, color: '#fca5a5', background: 'rgba(244,63,94,.08)', border: '1px solid rgba(244,63,94,.3)', borderRadius: 10, padding: '9px 14px', marginBottom: 20, display: 'inline-block' }}>
                            Reference: {this.state.ref}
                        </div>
                        <div>
                            <button
                                onClick={() => { this.setState({ error: null }); window.location.reload(); }}
                                style={{ background: '#7c3aed', color: '#fff', border: 0, padding: '11px 22px', borderRadius: 12, fontSize: 14, fontWeight: 600, cursor: 'pointer' }}
                            >
                                Reload page
                            </button>
                            <a href="/school/dashboard" style={{ marginLeft: 10, color: '#94a3b8', fontSize: 14, textDecoration: 'none', padding: '11px 8px' }}>
                                Go to dashboard
                            </a>
                        </div>
                    </div>
                </div>
            );
        }
        return this.props.children;
    }
}

createInertiaApp({
    resolve: name => {
        const pages = import.meta.glob('./Pages/**/*.tsx');
        return pages[`./Pages/${name}.tsx`]();
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <ErrorBoundary>
                <App {...props} />
            </ErrorBoundary>
        );
    },
    progress: {
        color: '#8b5cf6',
    },
});
