import React, { useMemo, useState } from 'react';
import { Search, ChevronUp, ChevronDown, Inbox } from 'lucide-react';

export interface Column<T> {
    key: string;
    header: string;
    render?: (row: T) => React.ReactNode;
    sortable?: boolean;
    sortValue?: (row: T) => string | number;
    className?: string;
}

interface Props<T> {
    columns: Column<T>[];
    rows: T[];
    searchable?: boolean;
    searchKeys?: (keyof T)[];
    rowHref?: (row: T) => string;
    emptyMessage?: string;
    pageSize?: number;
}

export default function DataTable<T extends Record<string, any>>({
    columns, rows, searchable = true, searchKeys, rowHref, emptyMessage = 'No records found.', pageSize = 15,
}: Props<T>) {
    const [query, setQuery] = useState('');
    const [sortKey, setSortKey] = useState<string | null>(null);
    const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc');
    const [page, setPage] = useState(0);

    const filtered = useMemo(() => {
        if (!query.trim()) return rows;
        const q = query.toLowerCase();
        const keys = searchKeys ?? (Object.keys(rows[0] ?? {}) as (keyof T)[]);
        return rows.filter(r => keys.some(k => String(r[k] ?? '').toLowerCase().includes(q)));
    }, [rows, query, searchKeys]);

    const sorted = useMemo(() => {
        if (!sortKey) return filtered;
        const col = columns.find(c => c.key === sortKey);
        if (!col) return filtered;
        const val = col.sortValue ?? ((r: T) => r[sortKey]);
        return [...filtered].sort((a, b) => {
            const av = val(a), bv = val(b);
            if (av < bv) return sortDir === 'asc' ? -1 : 1;
            if (av > bv) return sortDir === 'asc' ? 1 : -1;
            return 0;
        });
    }, [filtered, sortKey, sortDir, columns]);

    const pageCount = Math.ceil(sorted.length / pageSize);
    const paged = sorted.slice(page * pageSize, page * pageSize + pageSize);

    const toggleSort = (key: string) => {
        if (sortKey === key) setSortDir(d => (d === 'asc' ? 'desc' : 'asc'));
        else { setSortKey(key); setSortDir('asc'); }
    };

    const cellBase = 'px-4 py-3 text-sm';

    return (
        <div className="rounded-2xl border bg-white border-slate-200 dark:bg-white/[0.025] dark:border-white/5 overflow-hidden">
            {searchable && (
                <div className="px-4 py-3 border-b border-slate-200 dark:border-white/5">
                    <div className="relative max-w-xs">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                        <input
                            value={query}
                            onChange={e => { setQuery(e.target.value); setPage(0); }}
                            placeholder="Search…"
                            className="w-full pl-9 pr-3 py-2 text-sm rounded-lg bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-purple-500/30 dark:bg-white/5 dark:border-white/10 dark:text-slate-100"
                        />
                    </div>
                </div>
            )}

            <div className="overflow-x-auto">
                <table className="w-full">
                    <thead>
                        <tr className="border-b border-slate-200 dark:border-white/5">
                            {columns.map(col => (
                                <th
                                    key={col.key}
                                    onClick={() => col.sortable && toggleSort(col.key)}
                                    className={`${cellBase} text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 ${col.sortable ? 'cursor-pointer select-none' : ''} ${col.className ?? ''}`}
                                >
                                    <span className="inline-flex items-center gap-1">
                                        {col.header}
                                        {col.sortable && sortKey === col.key && (
                                            sortDir === 'asc' ? <ChevronUp className="w-3 h-3" /> : <ChevronDown className="w-3 h-3" />
                                        )}
                                    </span>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-white/5">
                        {paged.length === 0 ? (
                            <tr>
                                <td colSpan={columns.length} className="py-12 text-center">
                                    <Inbox className="w-8 h-8 mx-auto text-slate-300 dark:text-slate-600 mb-2" />
                                    <p className="text-sm text-slate-400">{emptyMessage}</p>
                                </td>
                            </tr>
                        ) : paged.map((row, i) => (
                            <tr
                                key={i}
                                onClick={rowHref ? () => { window.location.href = rowHref(row); } : undefined}
                                className={`${rowHref ? 'cursor-pointer' : ''} hover:bg-slate-50 dark:hover:bg-white/[0.02] transition`}
                            >
                                {columns.map(col => (
                                    <td key={col.key} className={`${cellBase} text-slate-700 dark:text-slate-200 ${col.className ?? ''}`}>
                                        {col.render ? col.render(row) : String(row[col.key] ?? '')}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {pageCount > 1 && (
                <div className="flex items-center justify-between px-4 py-3 border-t border-slate-200 dark:border-white/5 text-sm">
                    <span className="text-slate-500">
                        {page * pageSize + 1}–{Math.min((page + 1) * pageSize, sorted.length)} of {sorted.length}
                    </span>
                    <div className="flex gap-1">
                        <button
                            disabled={page === 0}
                            onClick={() => setPage(p => p - 1)}
                            className="px-3 py-1 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5 disabled:opacity-40 transition"
                        >Prev</button>
                        <button
                            disabled={page >= pageCount - 1}
                            onClick={() => setPage(p => p + 1)}
                            className="px-3 py-1 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5 disabled:opacity-40 transition"
                        >Next</button>
                    </div>
                </div>
            )}
        </div>
    );
}
