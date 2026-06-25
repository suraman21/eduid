import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';

function normHex(hex: string) {
  const h = hex.replace('#', '');
  return h.length === 3 ? h.split('').map(x => x + x).join('') : h;
}
function alpha(hex: string, a: number) {
  const h = normHex(hex);
  const r = parseInt(h.slice(0,2),16), g = parseInt(h.slice(2,4),16), b = parseInt(h.slice(4,6),16);
  return `rgba(${r},${g},${b},${a})`;
}

interface SessionRecord {
  id: number;
  student_name: string;
  student_uid: string;
  status: string;
  note: string;
}

interface SessionShow {
  id: number;
  class_name: string;
  class_id: number;
  session_date: string;
  session_date_raw: string;
  session_type: string;
  teacher_name: string;
  counts: { present: number; late: number; excused: number; absent: number };
  records: SessionRecord[];
}

interface Props extends PageProps {
  session: SessionShow;
}

const STATUS_BADGE: Record<string, { bg: string; color: string }> = {
  present: { bg: '#dcfce7', color: '#166534' },
  late:    { bg: '#fef3c7', color: '#92400e' },
  excused: { bg: '#dbeafe', color: '#1e40af' },
  absent:  { bg: '#fee2e2', color: '#991b1b' },
};

const STAT_TILES = [
  { key: 'present' as const, label: 'Present', bg: '#dcfce7', color: '#166534', numColor: '#16a34a' },
  { key: 'late'    as const, label: 'Late',    bg: '#fef3c7', color: '#92400e', numColor: '#d97706' },
  { key: 'excused' as const, label: 'Excused', bg: '#dbeafe', color: '#1e40af', numColor: '#2563eb' },
  { key: 'absent'  as const, label: 'Absent',  bg: '#fee2e2', color: '#991b1b', numColor: '#dc2626' },
];

export default function AttendanceShow({ session }: Props) {
  const { props } = usePage<PageProps>();
  const accent = (props as any).school?.accent_color ?? '#6366f1';

  const editUrl = `/school/attendance/create?class_id=${session.class_id}&session_date=${session.session_date_raw}&session_type=${session.session_type}`;

  const sessionTypeLabel = session.session_type === 'morning' ? 'Morning' : session.session_type === 'afternoon' ? 'Afternoon' : 'Full Day';

  return (
    <SchoolLayout>
      <Head title={`Attendance — ${session.class_name}`} />
      <div style={{ padding: '2rem', maxWidth: '900px', margin: '0 auto' }}>
        {/* Back + header */}
        <div style={{ marginBottom: '1.5rem' }}>
          <Link href="/school/attendance" style={{ color: '#9ca3af', fontSize: '0.875rem', textDecoration: 'none' }}>← Back to Attendance</Link>
          <h1 style={{ fontSize: '1.5rem', fontWeight: 700, color: '#111827', margin: '0.5rem 0 0' }}>
            {session.class_name} — {session.session_date}
          </h1>
          <div style={{ display: 'flex', gap: '1rem', alignItems: 'center', marginTop: '0.25rem' }}>
            <span style={{ color: '#6b7280', fontSize: '0.875rem' }}>{sessionTypeLabel} session</span>
            <span style={{ color: '#d1d5db' }}>·</span>
            <span style={{ color: '#6b7280', fontSize: '0.875rem' }}>Recorded by {session.teacher_name}</span>
            <span style={{ color: '#d1d5db' }}>·</span>
            <Link href={editUrl} style={{ color: accent, fontWeight: 600, fontSize: '0.875rem', textDecoration: 'none' }}>Edit attendance</Link>
          </div>
        </div>

        {/* Stat tiles */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '1rem', marginBottom: '1.5rem' }}>
          {STAT_TILES.map(tile => (
            <div key={tile.key} style={{ background: '#fff', borderRadius: '1.5rem', boxShadow: '0 1px 4px rgba(0,0,0,0.06)', padding: '1.25rem', textAlign: 'center' }}>
              <div style={{ fontSize: '2rem', fontWeight: 800, color: tile.numColor }}>{session.counts[tile.key]}</div>
              <div style={{ fontSize: '0.75rem', fontWeight: 600, color: tile.color, marginTop: '0.25rem' }}>{tile.label}</div>
            </div>
          ))}
        </div>

        {/* Records table */}
        <div style={{ background: '#fff', borderRadius: '1.5rem', boxShadow: '0 1px 4px rgba(0,0,0,0.06)', overflow: 'hidden' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr style={{ borderBottom: '1px solid #f3f4f6' }}>
                {['Student', 'Status', 'Note'].map(h => (
                  <th key={h} style={{ padding: '0.75rem 1rem', textAlign: 'left', fontSize: '0.75rem', fontWeight: 600, color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.05em' }}>{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {session.records.map(r => {
                const badge = STATUS_BADGE[r.status] ?? { bg: '#f3f4f6', color: '#6b7280' };
                return (
                  <tr key={r.id} style={{ borderBottom: '1px solid #f9fafb' }}>
                    <td style={{ padding: '0.75rem 1rem' }}>
                      <div style={{ fontWeight: 600, color: '#111827', fontSize: '0.875rem' }}>{r.student_name}</div>
                      <div style={{ color: '#9ca3af', fontSize: '0.75rem' }}>{r.student_uid}</div>
                    </td>
                    <td style={{ padding: '0.75rem 1rem' }}>
                      <span style={{ background: badge.bg, color: badge.color, borderRadius: '9999px', padding: '0.2rem 0.7rem', fontSize: '0.75rem', fontWeight: 600, textTransform: 'capitalize' }}>{r.status}</span>
                    </td>
                    <td style={{ padding: '0.75rem 1rem', color: '#6b7280', fontSize: '0.875rem' }}>{r.note || '—'}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>
    </SchoolLayout>
  );
}
