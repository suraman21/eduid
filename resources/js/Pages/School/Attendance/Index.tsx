import { Head, Link, router, usePage } from '@inertiajs/react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';

interface AttendanceSession {
  id: number;
  session_date: string;
  session_type: string;
  records_count: number;
  class_name: string | null;
  teacher_name: string | null;
}

interface SchoolClass { id: number; display_name: string; }

interface Props {
  sessions: {
    data: AttendanceSession[];
    links: { url: string | null; label: string; active: boolean }[];
    has_pages: boolean;
  };
  classes: SchoolClass[];
  filters: { class_id?: string; date?: string };
}

export default function AttendanceIndex({ sessions, classes, filters }: Props) {
  const { props } = usePage<PageProps>();
  const flash = props.flash;

  const handleFilter = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    const params: Record<string, string> = {};
    fd.forEach((v, k) => { if (v) params[k] = String(v); });
    router.get('/school/attendance', params, { preserveState: true, replace: true });
  };

  const hasFilters = filters.class_id || filters.date;

  return (
    <SchoolLayout>
      <Head title="Attendance" />

      {flash?.success && <div className="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{flash.success}</div>}
      {flash?.error   && <div className="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{flash.error}</div>}

      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
        <form onSubmit={handleFilter} className="flex flex-wrap items-center gap-2">
          <select name="class_id" defaultValue={filters.class_id ?? ''}
            className="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-600 bg-white focus:outline-none focus:border-indigo-400">
            <option value="">All Classes</option>
            {classes.map(c => <option key={c.id} value={c.id}>{c.display_name}</option>)}
          </select>
          <input type="date" name="date" defaultValue={filters.date ?? ''}
            className="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-600 bg-white focus:outline-none focus:border-indigo-400" />
          <button type="submit" className="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm transition">Filter</button>
          {hasFilters && (
            <Link href="/school/attendance" className="text-sm text-gray-400 hover:text-gray-600">Clear</Link>
          )}
        </form>
        <Link href="/school/attendance/create"
          className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2 flex-shrink-0">
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" /></svg>
          Take Attendance
        </Link>
      </div>

      <div className="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-100 text-left bg-gray-50">
                <th className="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Date</th>
                <th className="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Class</th>
                <th className="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Session</th>
                <th className="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Recorded By</th>
                <th className="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Marked</th>
                <th className="px-5 py-3" />
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50">
              {sessions.data.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-5 py-12 text-center text-gray-400">
                    <svg className="w-12 h-12 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                    <p>No attendance recorded yet</p>
                    <Link href="/school/attendance/create" className="text-indigo-500 hover:text-indigo-600 text-sm mt-1 inline-block">Take attendance →</Link>
                  </td>
                </tr>
              ) : sessions.data.map(s => (
                <tr key={s.id} className="hover:bg-gray-50 transition">
                  <td className="px-5 py-3 text-gray-900 font-medium">{s.session_date}</td>
                  <td className="px-5 py-3 text-gray-600">{s.class_name ?? '—'}</td>
                  <td className="px-5 py-3">
                    <span className="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600 capitalize">{s.session_type.replace('_', ' ')}</span>
                  </td>
                  <td className="px-5 py-3 text-gray-500">{s.teacher_name ?? '—'}</td>
                  <td className="px-5 py-3 text-gray-500">{s.records_count} student{s.records_count === 1 ? '' : 's'}</td>
                  <td className="px-5 py-3">
                    <Link href={`/school/attendance/${s.id}`} className="text-gray-400 hover:text-indigo-500 transition" title="View">
                      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {sessions.has_pages && (
          <div className="px-5 py-3 border-t border-gray-100 flex gap-1">
            {sessions.links.map((link, i) => (
              link.url
                ? <Link key={i} href={link.url} dangerouslySetInnerHTML={{ __html: link.label }}
                    className={`px-3 py-1 rounded text-sm ${link.active ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:bg-gray-100'}`} />
                : <span key={i} dangerouslySetInnerHTML={{ __html: link.label }}
                    className="px-3 py-1 rounded text-sm text-gray-300" />
            ))}
          </div>
        )}
      </div>
    </SchoolLayout>
  );
}
