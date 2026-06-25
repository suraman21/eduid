import { Head, Link, router, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';

interface QueueStudent {
  id: number;
  full_name: string;
  uid: string;
  photo_url: string | null;
}

interface Props {
  session: {
    id: number;
    session_type: string;
    status: string;
    class_name: string | null;
    photos_captured: number;
    total_students: number;
    progress: number;
    current_position: number;
    student_queue: number[];
    is_active: boolean;
    created_by_name: string | null;
  };
  currentStudent: {
    id: number;
    full_name: string;
    uid: string;
    class_name: string | null;
    photo_url: string | null;
  } | null;
  queueStudents: Record<number, QueueStudent>;
}

export default function PhotoSessionShow({ session, currentStudent, queueStudents }: Props) {
  const { props } = usePage<PageProps>();
  const flash = props.flash;
  const photoRef = useRef<HTMLInputElement>(null);
  const zipRef   = useRef<HTMLInputElement>(null);

  const uploadPhoto = (e: React.FormEvent) => {
    e.preventDefault();
    const file = photoRef.current?.files?.[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('photo', file);
    router.post(`/school/photo-sessions/${session.id}/upload-photo`, fd as any, { forceFormData: true });
  };

  const uploadZip = (e: React.FormEvent) => {
    e.preventDefault();
    const file = zipRef.current?.files?.[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('zip_file', file);
    router.post(`/school/photo-sessions/${session.id}/upload-zip`, fd as any, { forceFormData: true });
  };

  const skipStudent = () => router.post(`/school/photo-sessions/${session.id}/skip`);
  const complete    = () => router.post(`/school/photo-sessions/${session.id}/complete`);
  const cancel      = () => {
    if (confirm('Cancel this session? Progress is kept but the session will close.'))
      router.post(`/school/photo-sessions/${session.id}/cancel`);
  };

  const isCompleted = session.status === 'completed';

  return (
    <SchoolLayout>
      <Head title={`Photo Session #${session.id}`} />

      {flash?.success && <div className="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{flash.success}</div>}
      {flash?.error   && <div className="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{flash.error}</div>}
      {(props.flash as any)?.info && <div className="mb-4 bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg text-sm">{(props.flash as any).info}</div>}

      {!session.is_active && (
        <div className={`mb-6 ${isCompleted ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200'} border rounded-xl px-5 py-4 flex items-center justify-between`}>
          <div>
            <p className={`font-medium ${isCompleted ? 'text-blue-800' : 'text-gray-800'}`}>Session {session.status.charAt(0).toUpperCase() + session.status.slice(1)}</p>
            <p className={`text-sm ${isCompleted ? 'text-blue-600' : 'text-gray-600'} mt-0.5`}>{session.photos_captured} of {session.total_students} photos captured</p>
          </div>
          <Link href="/school/photo-sessions" className="text-sm text-indigo-600 hover:text-indigo-800 font-medium">← Back to sessions</Link>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2 space-y-5">

          {/* Progress card */}
          <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
            <div className="flex items-center justify-between mb-3">
              <h3 className="text-sm font-semibold text-gray-700">Progress</h3>
              <span className="text-sm text-gray-500">{session.photos_captured} / {session.total_students} photos</span>
            </div>
            <div className="w-full bg-gray-100 rounded-full h-2">
              <div className="bg-indigo-500 h-2 rounded-full transition-all" style={{ width: `${session.progress}%` }} />
            </div>
            <p className="text-xs text-gray-400 mt-2">{Math.round(session.progress)}% complete</p>
          </div>

          {session.is_active && (
            <>
              {session.session_type === 'app_capture' ? (
                currentStudent ? (
                  <div className="bg-white rounded-xl border border-indigo-100 shadow-sm p-6">
                    <p className="text-xs font-medium text-indigo-500 uppercase tracking-wide mb-1">
                      Student {session.current_position + 1} of {session.total_students}
                    </p>
                    <div className="flex items-center gap-4 mb-5">
                      <div className="w-16 h-16 rounded-full bg-gray-100 overflow-hidden flex items-center justify-center flex-shrink-0">
                        {currentStudent.photo_url
                          ? <img src={currentStudent.photo_url} alt={currentStudent.full_name} className="w-full h-full object-cover" />
                          : <svg className="w-8 h-8 text-gray-300" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z" /></svg>
                        }
                      </div>
                      <div>
                        <h2 className="text-xl font-bold text-gray-800">{currentStudent.full_name}</h2>
                        <p className="text-sm text-gray-400">{currentStudent.uid} · {currentStudent.class_name ?? 'No class'}</p>
                      </div>
                    </div>
                    <form onSubmit={uploadPhoto}>
                      <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">Upload photo for this student</label>
                        <input ref={photoRef} type="file" name="photo" accept="image/*" required
                          className="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                      </div>
                      <div className="flex items-center gap-3">
                        <button type="submit" className="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition">Save & Next →</button>
                        <button type="button" onClick={skipStudent} className="text-sm text-gray-400 hover:text-gray-600 transition">Skip this student</button>
                      </div>
                    </form>
                  </div>
                ) : (
                  <div className="bg-green-50 border border-green-200 rounded-xl p-6 text-center">
                    <svg className="w-10 h-10 text-green-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p className="font-semibold text-green-800">All students processed!</p>
                    <p className="text-sm text-green-600 mt-1">Click below to mark this session as complete.</p>
                    <button onClick={complete} className="mt-4 bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition">Complete Session</button>
                  </div>
                )
              ) : (
                <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                  <h3 className="text-sm font-semibold text-gray-700 mb-1">Upload ZIP File</h3>
                  <p className="text-xs text-gray-500 mb-4">
                    Name your photos <code className="bg-gray-100 px-1 rounded">001.jpg</code>, <code className="bg-gray-100 px-1 rounded">002.jpg</code>… matching the alphabetical student order shown in the queue. Then zip them and upload here.
                  </p>
                  <form onSubmit={uploadZip}>
                    <div className="mb-4">
                      <input ref={zipRef} type="file" name="zip_file" accept=".zip" required
                        className="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                    </div>
                    <button type="submit" className="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition">Upload & Match</button>
                  </form>
                </div>
              )}

              <div className="flex items-center gap-4">
                <button onClick={complete} className="text-sm text-green-600 hover:text-green-800 font-medium">Mark complete</button>
                <button onClick={cancel} className="text-sm text-red-400 hover:text-red-600">Cancel session</button>
              </div>
            </>
          )}
        </div>

        {/* Student queue */}
        <div className="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
          <div className="px-4 py-3 border-b border-gray-100 bg-gray-50">
            <h3 className="text-sm font-semibold text-gray-700">Student Queue</h3>
            <p className="text-xs text-gray-400 mt-0.5">Alphabetical order</p>
          </div>
          <ul className="divide-y divide-gray-50 max-h-[520px] overflow-y-auto">
            {session.student_queue.map((studentId, pos) => {
              const stu = queueStudents[studentId];
              const isCurrent = pos === session.current_position && session.is_active;
              const isDone = pos < session.current_position || !!stu?.photo_url;
              return (
                <li key={pos} className={`px-4 py-2.5 flex items-center gap-3 ${isCurrent ? 'bg-indigo-50' : ''}`}>
                  <span className="text-xs text-gray-300 w-5 text-right flex-shrink-0">{pos + 1}</span>
                  <div className="w-7 h-7 rounded-full overflow-hidden bg-gray-100 flex-shrink-0 flex items-center justify-center">
                    {stu?.photo_url
                      ? <img src={stu.photo_url} alt="" className="w-full h-full object-cover" />
                      : <svg className="w-3.5 h-3.5 text-gray-300" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z" /></svg>
                    }
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className={`text-sm truncate ${isCurrent ? 'font-semibold text-indigo-700' : 'text-gray-700'}`}>{stu?.full_name ?? 'Unknown'}</p>
                    <p className="text-xs text-gray-400 truncate">{stu?.uid}</p>
                  </div>
                  {isDone
                    ? <svg className="w-4 h-4 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7" /></svg>
                    : isCurrent
                    ? <span className="w-2 h-2 rounded-full bg-indigo-500 flex-shrink-0" />
                    : null
                  }
                </li>
              );
            })}
          </ul>
        </div>
      </div>
    </SchoolLayout>
  );
}
