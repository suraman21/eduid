import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import SchoolLayout from '@/Layouts/SchoolLayout';

interface SchoolClass { id: number; display_name: string; }
interface Student { id: number; full_name: string; uid: string; first_name: string; last_name: string; }

interface Props {
  classes: SchoolClass[];
  class: { id: number; display_name: string } | null;
  roster: Student[];
  existing: Record<number, string>;
  sessionDate: string;
  sessionType: string;
  sessionTypes?: string[];
}

const STATUSES = ['present', 'late', 'excused', 'absent'] as const;
const STATUS_COLORS: Record<string, string> = {
  present: 'peer-checked:bg-green-50 peer-checked:text-green-600 peer-checked:border-green-300',
  late:    'peer-checked:bg-amber-50 peer-checked:text-amber-600 peer-checked:border-amber-300',
  excused: 'peer-checked:bg-blue-50 peer-checked:text-blue-600 peer-checked:border-blue-300',
  absent:  'peer-checked:bg-red-50 peer-checked:text-red-600 peer-checked:border-red-300',
};

export default function AttendanceCreate({ classes, class: selectedClass, roster, existing, sessionDate, sessionType, sessionTypes = ['morning', 'afternoon'] }: Props) {
  const typeLabel = (t: string) => t.split('_').map(w => w[0].toUpperCase() + w.slice(1)).join(' ');
  const [filterState, setFilterState] = useState({ class_id: selectedClass?.id?.toString() ?? '', session_date: sessionDate, session_type: sessionType });
  const [statuses, setStatuses] = useState<Record<number, string>>(() => {
    const init: Record<number, string> = {};
    roster.forEach(s => { init[s.id] = existing[s.id] ?? 'present'; });
    return init;
  });

  const loadRoster = (e: React.FormEvent) => {
    e.preventDefault();
    router.get('/school/attendance/create', filterState, { preserveScroll: true });
  };

  const markAll = (status: string) => {
    const next: Record<number, string> = {};
    roster.forEach(s => { next[s.id] = status; });
    setStatuses(next);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const records = roster.map(s => ({ student_id: s.id, status: statuses[s.id] ?? 'present' }));
    router.post('/school/attendance', {
      class_id: selectedClass!.id,
      session_date: sessionDate,
      session_type: sessionType,
      records,
    });
  };

  return (
    <SchoolLayout>
      <Head title="Take Attendance" />

      {/* Step 1: Filter form */}
      <form onSubmit={loadRoster} className="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-6">
        <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Class</label>
            <select required value={filterState.class_id} onChange={e => setFilterState(p => ({ ...p, class_id: e.target.value }))}
              className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-indigo-400">
              <option value="">Select class…</option>
              {classes.map(c => <option key={c.id} value={c.id}>{c.display_name}</option>)}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Date</label>
            <input type="date" value={filterState.session_date} max={new Date().toISOString().slice(0, 10)}
              onChange={e => setFilterState(p => ({ ...p, session_date: e.target.value }))}
              className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-indigo-400" />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Session</label>
            <select value={filterState.session_type} onChange={e => setFilterState(p => ({ ...p, session_type: e.target.value }))}
              className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-indigo-400">
              {sessionTypes.map(t => <option key={t} value={t}>{typeLabel(t)}</option>)}
            </select>
          </div>
          <div>
            <button type="submit" className="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">Load Roster</button>
          </div>
        </div>
      </form>

      {selectedClass && (
        roster.length === 0 ? (
          <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
            <p>No active students in {selectedClass.display_name}.</p>
            <Link href="/school/students" className="text-indigo-500 hover:text-indigo-600 text-sm mt-1 inline-block">Manage students →</Link>
          </div>
        ) : (
          <form onSubmit={handleSubmit}>
            <div className="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
              <div className="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                <div>
                  <p className="font-medium text-gray-900">{selectedClass.display_name}</p>
                  <p className="text-xs text-gray-400">{sessionDate} · {sessionType.replace('_', ' ')} · {roster.length} students</p>
                </div>
                <div className="flex items-center gap-2 text-xs">
                  <span className="text-gray-400">Mark all:</span>
                  <button type="button" onClick={() => markAll('present')} className="px-2 py-1 rounded bg-green-50 text-green-600 hover:bg-green-100">Present</button>
                  <button type="button" onClick={() => markAll('absent')} className="px-2 py-1 rounded bg-red-50 text-red-600 hover:bg-red-100">Absent</button>
                </div>
              </div>

              <div className="divide-y divide-gray-50">
                {roster.map(student => (
                  <div key={student.id} className="px-5 py-3 flex flex-col sm:flex-row sm:items-center gap-3">
                    <div className="flex items-center gap-3 flex-1 min-w-0">
                      <div className="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-500 flex-shrink-0">
                        {student.first_name[0]?.toUpperCase()}{student.last_name[0]?.toUpperCase()}
                      </div>
                      <div className="min-w-0">
                        <p className="font-medium text-gray-900 truncate">{student.full_name}</p>
                        <p className="text-xs text-gray-400 font-mono">{student.uid}</p>
                      </div>
                    </div>
                    <div className="flex items-center gap-1 flex-shrink-0">
                      {STATUSES.map(status => (
                        <label key={status} className="cursor-pointer">
                          <input type="radio" className="peer sr-only" checked={statuses[student.id] === status}
                            onChange={() => setStatuses(p => ({ ...p, [student.id]: status }))} />
                          <span className={`px-3 py-1 rounded-lg text-xs capitalize border border-gray-200 text-gray-500 hover:bg-gray-50 transition inline-block ${STATUS_COLORS[status]}`}>
                            {status}
                          </span>
                        </label>
                      ))}
                    </div>
                  </div>
                ))}
              </div>
            </div>

            <div className="flex items-center gap-3 mt-6">
              <button type="submit" className="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition">Save Attendance</button>
              <Link href="/school/attendance" className="text-sm text-gray-500 hover:text-gray-700">Cancel</Link>
            </div>
          </form>
        )
      )}
    </SchoolLayout>
  );
}
