import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import React from 'react';
import '../css/app.css';

/**
 * App-wide error boundary. A render-time exception in any page would otherwise
 * unmount everything and leave a blank screen; here we catch it and show a
 * readable message + reload, so a single component bug never blanks the app.
 */
class ErrorBoundary extends React.Component<{ children: React.ReactNode }, { error: Error | null }> {
    state = { error: null as Error | null };

    static getDerivedStateFromError(error: Error) {
        return { error };
    }

    componentDidCatch(error: Error, info: unknown) {
        // Surface in the console for diagnosis.
        console.error('Unhandled UI error:', error, info);
    }

    render() {
        if (this.state.error) {
            return (
                <div style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#0f172a', color: '#e2e8f0', fontFamily: 'system-ui, sans-serif', padding: 24 }}>
                    <div style={{ maxWidth: 520, textAlign: 'center' }}>
                        <h1 style={{ fontSize: 18, fontWeight: 700, marginBottom: 8 }}>Something went wrong on this page</h1>
                        <p style={{ fontSize: 13, color: '#94a3b8', marginBottom: 16 }}>
                            The page hit an unexpected error and couldn’t render. This has been logged to the browser console.
                        </p>
                        <pre style={{ fontSize: 11, color: '#f87171', background: '#1e293b', padding: 12, borderRadius: 8, overflow: 'auto', textAlign: 'left', marginBottom: 16 }}>
                            {String(this.state.error?.message ?? this.state.error)}
                        </pre>
                        <button
                            onClick={() => { this.setState({ error: null }); window.location.reload(); }}
                            style={{ background: '#7c3aed', color: '#fff', border: 0, padding: '10px 20px', borderRadius: 10, fontSize: 14, fontWeight: 600, cursor: 'pointer' }}
                        >
                            Reload page
                        </button>
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
