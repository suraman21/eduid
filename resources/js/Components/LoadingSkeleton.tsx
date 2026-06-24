interface Props {
    rows?: number;
    className?: string;
}

export default function LoadingSkeleton({ rows = 3, className = '' }: Props) {
    return (
        <div className={`space-y-3 ${className}`}>
            {Array.from({ length: rows }).map((_, i) => (
                <div key={i} className="h-12 rounded-xl bg-slate-100 dark:bg-white/5 animate-pulse" />
            ))}
        </div>
    );
}
