import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';

// inline helpers
function normHex(hex: string) {
  const h = hex.replace('#', '');
  return h.length === 3 ? h.split('').map(x => x + x).join('') : h;
}
function alpha(hex: string, a: number) {
  const h = normHex(hex);
  const r = parseInt(h.slice(0,2),16), g = parseInt(h.slice(2,4),16), b = parseInt(h.slice(4,6),16);
  return `rgba(${r},${g},${b},${a})`;
}

interface Session {
  id: number;
  session_date: string;
  class_name: string;
  session_type: string;
  teacher_name: string;
  records_count: number;
}

interface Props extends PageProps {
  sessions: { data: Session[]; links: any[]; meta: any };
  classes: { id: number; name: string }[];
  filters: { class_id?: string; date?: string };
}

const sessionBadge = (type: string) => {
  if (type === 'morning') return { bg: '#e0e7ff', color: '#3730a3', label: 'Morning' };
  if (type === 'afternoon') return { bg: '#fef3c7', color: '#92400e', label: 'Afternoon' };
  return { bg: '#d1fae5', color: '#065f46', label: 'Full Day' };
};

export default function AttendanceIndex({ sessions, classes, filters }: Props) {
  const { props } = usePage<PageProps>();
  const accent = (props as any).school?.accent_color ?? '#6366f1';
  const [classId, setClassId] = useState(filters.class_id ?? '');
  const [date, setDate] = useState(filters.date ?? '');

  function applyFilters(e: React.FormEvent) {
    e.preventDefault();
    router.get('/school/attendance', { class_id: classId, date }, { preserveState: true, replace: true });
  }

  return (
    <SchoolLayout>
      <Head title="Attendance" />
      <div style={{ padding: '2rem' }}>
        {/* Header */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem' }}>
          <div>
            <h1 style={{ fontSize: '1.5rem', fontWeight: 700, color: '#111827', margin: 0 }}>Attendance</h1>
            <p style={{ color: '#6b7280', margin: '0.25rem 0 0' }}>Track daily attendance sessions</p>
          </div>
          <Link
            href="/school/attendance/create"
            style={{ background: accent, color: '#fff', borderRadius: '0.75rem', padding: '0.6rem 1.25rem', fontWeight: 600, textDecoration: 'none', fontSize: '0.875rem' }}
          >
            Take Attendance
          </Link>
        </div>

        {/* Filters */}
        <form onSubmit={applyFilters} style={{ background: '#fff', borderRadius: '1.5rem', padding: '1.25rem', boxShadow: '0 1px 4px rgba(0,0,0,0.06)', marginBottom: '1.5rem', display: 'flex', gap: '0.75rem', flexWrap: 'wrap', alignItems: 'flex-end' }}>
          <div style={{ flex: '1', minWidth: '160px' }}>
            <label style={{ display: 'block', fontSize: '0.75rem', fontWeight: 600, color: '#6b7280', marginBottom: '0.25rem' }}>Class</label>
            <select value={classId} onChange={e => setClassId(e.target.value)} style={{ width: '100%', border: '1px solid #e5e7eb', borderRadius: '0.75rem', padding: '0.5rem 0.75rem', fontSize: '0.875rem' }}>
              <option value="">All Classes</option>
              {classes.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
          </div>
          <div style={{ flex: '1', minWidth: '160px' }}>
            <label style={{ display: 'block', fontSize: '0.75rem', fontWeight: 600, color: '#6b7280', marginBottom: '0.25rem' }}>Date</label>
            <input type="date" value={date} onChange={e => setDate(e.target.value)} style={{ width: '100%', border: '1px solid #e5e7eb', borderRadius: '0.75rem', padding: '0.5rem 0.75rem', fontSize: '0.875rem' }} />
          </div>
          <button type="submit" style={{ background: alpha(accent, 0.1), color: accent, border: 'none', borderRadius: '0.75rem', padding: '0.5rem 1.25rem', fontWeight: 600, cursor: 'pointer', fontSize: '0.875rem' }}>
            Filter
          </button>
        </form>

        {/* Table */}
        <div style={{ background: '#fff', borderRadius: '1.5rem', boxShadow: '0 1px 4px rgba(0,0,0,0.06)', overflow: 'hidden' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr style={{ borderBottom: '1px solid #f3f4f6' }}>
                {['Date', 'Class', 'Session', 'Recorded By', 'Marked', ''].map(h => (
                  <th key={h} style={{ padding: '0.75rem 1rem', textAlign: 'left', fontSize: '0.75rem', fontWeight: 600, color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.05em' }}>{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {sessions.data.length === 0 ? (
                <tr><td colSpan={6} style={{ padding: '3rem', textAlign: 'center', color: '#9ca3af' }}>No attendance sessions found</td></tr>
              ) : sessions.data.map(s => {
                const badge = sessionBadge(s.session_type);
                return (
                  <tr key={s.id} style={{ borderBottom: '1px solid #f9fafb' }}>
                    <td style={{ padding: '0.75rem 1rem', fontSize: '0.875rem', color: '#111827' }}>{s.session_date}</td>
                    <td style={{ padding: '0.75rem 1rem', fontSize: '0.875rem', color: '#374151' }}>{s.class_name}</td>
                    <td style={{ padding: '0.75rem 1rem' }}>
                      <span style={{ background: badge.bg, color: badge.color, borderRadius: '9999px', padding: '0.2rem 0.7rem', fontSize: '0.75rem', fontWeight: 600 }}>{badge.label}</span>
                    </td>
                    <td style={{ padding: '0.75rem 1rem', fontSize: '0.875rem', color: '#6b7280' }}>{s.teacher_name}</td>
                    <td style={{ padding: '0.75rem 1rem', fontSize: '0.875rem', color: '#374151' }}>{s.records_count} students</td>
                    <td style={{ padding: '0.75rem 1rem' }}>
                      <Link href={`/school/attendance/${s.id}`} style={{ color: accent, fontWeight: 600, fontSize: '0.875rem', textDecoration: 'none' }}>View</Link>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {sessions.meta?.last_page > 1 && (
          <div style={{ display: 'flex', gap: '0.5rem', justifyContent: 'center', marginTop: '1.5rem' }}>
            {sessions.links.map((link: any, i: number) => (
              <button
                key={i}
                disabled={!link.url}
                onClick={() => link.url && router.get(link.url)}
                dangerouslySetInnerHTML={{ __html: link.label }}
                style={{
                  padding: '0.4rem 0.75rem', borderRadius: '0.5rem', border: 'none', cursor: link.url ? 'pointer' : 'default',
                  background: link.active ? accent : '#f3f4f6',
                  color: link.active ? '#fff' : link.url ? '#374151' : '#9ca3af',
                  fontSize: '0.875rem', fontWeight: link.active ? 700 : 400,
                }}
              />
            ))}
          </div>
        )}
      </div>
    </SchoolLayout>
  );
}
