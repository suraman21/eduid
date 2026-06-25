import { Head, Link, usePage } from '@inertiajs/react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';

interface PhotoSession {
  id: number;
  session_type: string;
  class_name: string | null;
  status: string;
  photos_captured: number;
  total_students: number;
  progress: number;
  created_at_human: string;
  is_active: boolean;
}

interface Props {
  sessions: {
    data: PhotoSession[];
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
    has_pages: boolean;
  };
}

export default function PhotoSessionsIndex({ sessions }: Props) {
  const { props } = usePage<PageProps>();
  const flash = props.flash;
  const isAdmin = (props.auth.user?.role === 'school_admin' || props.auth.user?.role === 'super_admin');

  return (
    <SchoolLayout>
      <Head title="Photo Sessions" />

      {flash?.success && <div className="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{flash.success}</div>}
      {flash?.error   && <div className="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{flash.error}</div>}

      <div className="flex items-center justify-between mb-6">
        <p className="text-sm text-gray-400">{sessions.total} session{sessions.total === 1 ? '' : 's'}</p>
        {isAdmin && (
          <Link href="/school/photo-sessions/create"
            className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" /></svg>
            New Session
          </Link>
        )}
      </div>

      <div className="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        {sessions.data.length === 0 ? (
          <div className="px-6 py-16 text-center text-gray-400">
            <svg className="w-12 h-12 mx-auto mb-4 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <p className="font-medium text-gray-500">No photo sessions yet</p>
            <p className="text-sm mt-1">Start a session to photograph students and link photos to their profiles.</p>
          </div>
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-gray-100 bg-gray-50 text-left">
                    <th className="px-5 py-3 font-medium text-gray-500">Session</th>
                    <th className="px-5 py-3 font-medium text-gray-500">Type</th>
                    <th className="px-5 py-3 font-medium text-gray-500">Class</th>
                    <th className="px-5 py-3 font-medium text-gray-500">Progress</th>
                    <th className="px-5 py-3 font-medium text-gray-500">Status</th>
                    <th className="px-5 py-3 font-medium text-gray-500">Created</th>
                    <th className="px-5 py-3" />
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {sessions.data.map(s => (
                    <tr key={s.id} className="hover:bg-gray-50 transition">
                      <td className="px-5 py-3 text-gray-700 font-medium">#{s.id}</td>
                      <td className="px-5 py-3 text-gray-600">{s.session_type === 'app_capture' ? 'Step-by-step' : 'ZIP Upload'}</td>
                      <td className="px-5 py-3 text-gray-600">{s.class_name ?? 'Whole School'}</td>
                      <td className="px-5 py-3">
                        <div className="flex items-center gap-2">
                          <div className="w-24 bg-gray-100 rounded-full h-1.5">
                            <div className="bg-indigo-500 h-1.5 rounded-full" style={{ width: `${s.progress}%` }} />
                          </div>
                          <span className="text-gray-500 text-xs">{s.photos_captured}/{s.total_students}</span>
                        </div>
                      </td>
                      <td className="px-5 py-3">
                        {s.status === 'active'
                          ? <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700"><span className="w-1.5 h-1.5 rounded-full bg-green-500" /> Active</span>
                          : s.status === 'completed'
                          ? <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700">Completed</span>
                          : <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Cancelled</span>
                        }
                      </td>
                      <td className="px-5 py-3 text-gray-400 text-xs">{s.created_at_human}</td>
                      <td className="px-5 py-3 text-right">
                        <Link href={`/school/photo-sessions/${s.id}`} className="text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                          {s.is_active ? 'Continue →' : 'View →'}
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {sessions.has_pages && (
              <div className="px-5 py-4 border-t border-gray-100 flex gap-1">
                {sessions.links.map((link, i) => (
                  link.url
                    ? <Link key={i} href={link.url} dangerouslySetInnerHTML={{ __html: link.label }}
                        className={`px-3 py-1 rounded text-sm ${link.active ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:bg-gray-100'}`} />
                    : <span key={i} dangerouslySetInnerHTML={{ __html: link.label }} className="px-3 py-1 rounded text-sm text-gray-300" />
                ))}
              </div>
            )}
          </>
        )}
      </div>
    </SchoolLayout>
  );
}
