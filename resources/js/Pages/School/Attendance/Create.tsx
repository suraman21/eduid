import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
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

interface Student { id: number; name: string; uid: string; initials: string }
interface ClassOption { id: number; name: string }

interface Props extends PageProps {
  classes: ClassOption[];
  class: ClassOption | null;
  roster: Student[];
  existing: Record<number, string>;
  sessionDate: string;
  sessionType: string;
}

type StatusType = 'present' | 'late' | 'excused' | 'absent';

const STATUS_CONFIG: Record<StatusType, { label: string; bg: string; color: string; activeBg: string }> = {
  present: { label: 'Present', bg: '#f0fdf4', color: '#166534', activeBg: '#22c55e' },
  late:    { label: 'Late',    bg: '#fffbeb', color: '#92400e', activeBg: '#f59e0b' },
  excused: { label: 'Excused', bg: '#eff6ff', color: '#1e40af', activeBg: '#3b82f6' },
  absent:  { label: 'Absent',  bg: '#fef2f2', color: '#991b1b', activeBg: '#ef4444' },
};

interface RosterFormProps {
  class_: ClassOption;
  roster: Student[];
  existing: Record<number, string>;
  sessionDate: string;
  sessionType: string;
  accent: string;
}

function RosterForm({ class_, roster, existing, sessionDate, sessionType, accent }: RosterFormProps) {
  const { data, setData, post, processing } = useForm<{
    class_id: number;
    session_date: string;
    session_type: string;
    records: { student_id: number; status: string; note: string }[];
  }>({
    class_id: class_.id,
    session_date: sessionDate,
    session_type: sessionType,
    records: roster.map(s => ({
      student_id: s.id,
      status: existing[s.id] ?? 'present',
      note: '',
    })),
  });

  function markAll(status: string) {
    setData('records', data.records.map(r => ({ ...r, status })));
  }

  function setStatus(i: number, status: string) {
    const records = [...data.records];
    records[i] = { ...records[i], status };
    setData('records', records);
  }

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post('/school/attendance');
  }

  if (roster.length === 0) {
    return (
      <div style={{ background: '#fff', borderRadius: '1.5rem', boxShadow: '0 1px 4px rgba(0,0,0,0.06)', padding: '3rem', textAlign: 'center', color: '#9ca3af' }}>
        No active students in this class.
      </div>
    );
  }

  return (
    <form onSubmit={submit}>
      <div style={{ background: '#fff', borderRadius: '1.5rem', boxShadow: '0 1px 4px rgba(0,0,0,0.06)', overflow: 'hidden' }}>
        <div style={{ padding: '1.25rem 1.5rem', borderBottom: '1px solid #f3f4f6', display: 'flex', gap: '0.75rem', alignItems: 'center' }}>
          <span style={{ fontWeight: 600, color: '#374151', flex: 1 }}>Mark Attendance — {class_.name}</span>
          <button type="button" onClick={() => markAll('present')} style={{ background: '#f0fdf4', color: '#166534', border: 'none', borderRadius: '0.75rem', padding: '0.4rem 1rem', fontWeight: 600, cursor: 'pointer', fontSize: '0.8rem' }}>Mark all Present</button>
          <button type="button" onClick={() => markAll('absent')} style={{ background: '#fef2f2', color: '#991b1b', border: 'none', borderRadius: '0.75rem', padding: '0.4rem 1rem', fontWeight: 600, cursor: 'pointer', fontSize: '0.8rem' }}>Mark all Absent</button>
        </div>
        {roster.map((student, i) => (
          <div key={student.id} style={{ display: 'flex', alignItems: 'center', gap: '1rem', padding: '0.875rem 1.5rem', borderBottom: '1px solid #f9fafb' }}>
            <div style={{ width: 38, height: 38, borderRadius: '50%', background: alpha(accent, 0.15), color: accent, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 700, fontSize: '0.875rem', flexShrink: 0 }}>
              {student.initials}
            </div>
            <div style={{ flex: 1 }}>
              <div style={{ fontWeight: 600, color: '#111827', fontSize: '0.875rem' }}>{student.name}</div>
              <div style={{ color: '#9ca3af', fontSize: '0.75rem' }}>{student.uid}</div>
            </div>
            <div style={{ display: 'flex', gap: '0.4rem' }}>
              {(Object.keys(STATUS_CONFIG) as StatusType[]).map(st => {
                const cfg = STATUS_CONFIG[st];
                const active = data.records[i]?.status === st;
                return (
                  <button
                    key={st}
                    type="button"
                    onClick={() => setStatus(i, st)}
                    style={{
                      padding: '0.3rem 0.75rem',
                      borderRadius: '9999px',
                      border: active ? `2px solid ${cfg.activeBg}` : '2px solid transparent',
                      background: active ? cfg.activeBg : cfg.bg,
                      color: active ? '#fff' : cfg.color,
                      fontWeight: 600,
                      cursor: 'pointer',
                      fontSize: '0.75rem',
                      transition: 'all 0.15s',
                    }}
                  >
                    {cfg.label}
                  </button>
                );
              })}
            </div>
          </div>
        ))}
        <div style={{ padding: '1.25rem 1.5rem', display: 'flex', justifyContent: 'flex-end' }}>
          <button type="submit" disabled={processing} style={{ background: accent, color: '#fff', border: 'none', borderRadius: '0.75rem', padding: '0.65rem 1.5rem', fontWeight: 700, cursor: 'pointer', fontSize: '0.875rem', opacity: processing ? 0.7 : 1 }}>
            {processing ? 'Saving…' : 'Save Attendance'}
          </button>
        </div>
      </div>
    </form>
  );
}

export default function AttendanceCreate({ classes, class: class_, roster, existing, sessionDate, sessionType }: Props) {
  const { props } = usePage<PageProps>();
  const accent = (props as any).school?.accent_color ?? '#6366f1';
  const today = new Date().toISOString().slice(0, 10);

  const [filterClassId, setFilterClassId] = useState(class_?.id?.toString() ?? '');
  const [filterDate, setFilterDate] = useState(sessionDate);
  const [filterSessionType, setFilterSessionType] = useState(sessionType);

  function loadRoster(e: React.FormEvent) {
    e.preventDefault();
    router.get('/school/attendance/create', {
      class_id: filterClassId,
      session_date: filterDate,
      session_type: filterSessionType,
    }, { preserveState: false });
  }

  const rosterKey = class_ ? `${class_.id}-${sessionDate}-${sessionType}` : 'none';

  return (
    <SchoolLayout>
      <Head title="Take Attendance" />
      <div style={{ padding: '2rem', maxWidth: '900px', margin: '0 auto' }}>
        <div style={{ marginBottom: '1.5rem' }}>
          <h1 style={{ fontSize: '1.5rem', fontWeight: 700, color: '#111827', margin: 0 }}>Take Attendance</h1>
          <p style={{ color: '#6b7280', margin: '0.25rem 0 0' }}>Select a class and date to load the roster</p>
        </div>

        {/* Step 1: Filter form */}
        <form onSubmit={loadRoster} style={{ background: '#fff', borderRadius: '1.5rem', boxShadow: '0 1px 4px rgba(0,0,0,0.06)', padding: '1.5rem', marginBottom: '1.5rem' }}>
          <div style={{ fontWeight: 600, color: '#374151', marginBottom: '1rem' }}>Step 1 — Select Class & Date</div>
          <div style={{ display: 'flex', gap: '0.75rem', flexWrap: 'wrap', alignItems: 'flex-end' }}>
            <div style={{ flex: '1', minWidth: '180px' }}>
              <label style={{ display: 'block', fontSize: '0.75rem', fontWeight: 600, color: '#6b7280', marginBottom: '0.25rem' }}>Class</label>
              <select value={filterClassId} onChange={e => setFilterClassId(e.target.value)} required style={{ width: '100%', border: '1px solid #e5e7eb', borderRadius: '0.75rem', padding: '0.5rem 0.75rem', fontSize: '0.875rem' }}>
                <option value="">Select class…</option>
                {classes.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
              </select>
            </div>
            <div style={{ flex: '1', minWidth: '160px' }}>
              <label style={{ display: 'block', fontSize: '0.75rem', fontWeight: 600, color: '#6b7280', marginBottom: '0.25rem' }}>Date</label>
              <input type="date" value={filterDate} max={today} onChange={e => setFilterDate(e.target.value)} required style={{ width: '100%', border: '1px solid #e5e7eb', borderRadius: '0.75rem', padding: '0.5rem 0.75rem', fontSize: '0.875rem' }} />
            </div>
            <div style={{ flex: '1', minWidth: '160px' }}>
              <label style={{ display: 'block', fontSize: '0.75rem', fontWeight: 600, color: '#6b7280', marginBottom: '0.25rem' }}>Session</label>
              <select value={filterSessionType} onChange={e => setFilterSessionType(e.target.value)} style={{ width: '100%', border: '1px solid #e5e7eb', borderRadius: '0.75rem', padding: '0.5rem 0.75rem', fontSize: '0.875rem' }}>
                <option value="morning">Morning</option>
                <option value="afternoon">Afternoon</option>
                <option value="full_day">Full Day</option>
              </select>
            </div>
            <button type="submit" style={{ background: accent, color: '#fff', border: 'none', borderRadius: '0.75rem', padding: '0.55rem 1.25rem', fontWeight: 600, cursor: 'pointer', fontSize: '0.875rem' }}>
              Load Roster
            </button>
          </div>
        </form>

        {/* Step 2: Roster form */}
        {class_ && (
          <div key={rosterKey}>
            <div style={{ fontWeight: 600, color: '#374151', marginBottom: '1rem' }}>Step 2 — Mark Attendance</div>
            <RosterForm
              class_={class_}
              roster={roster}
              existing={existing}
              sessionDate={sessionDate}
              sessionType={sessionType}
              accent={accent}
            />
          </div>
        )}
      </div>
    </SchoolLayout>
  );
}
