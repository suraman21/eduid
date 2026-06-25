import { Head, Link } from '@inertiajs/react';
import SchoolLayout from '@/Layouts/SchoolLayout';

interface Record {
  student_name: string | null;
  student_uid: string | null;
  status: string;
  note: string | null;
}

interface Props {
  session: {
    id: number;
    class_name: string | null;
    session_date: string;
    session_type: string;
    teacher_name: string | null;
    class_id: number;
    present: number;
    late: number;
    excused: number;
    absent: number;
    records: Record[];
  };
}

const STATUS_COLORS: Record<string, string> = {
  present: 'bg-green-50 text-green-600',
  late:    'bg-amber-50 text-amber-600',
  excused: 'bg-blue-50 text-blue-600',
  absent:  'bg-red-50 text-red-600',
};

export default function AttendanceShow({ session }: Props) {
  const editUrl = `/school/attendance/create?class_id=${session.class_id}&session_date=${session.session_date}&session_type=${session.session_type}`;

  return (
    <SchoolLayout>
      <Head title={`${session.class_name ?? 'Attendance'} — ${session.session_date}`} />

      <div className="max-w-3xl space-y-6">
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
          {[
            { label: 'Present', value: session.present, cls: 'text-green-600' },
            { label: 'Late',    value: session.late,    cls: 'text-amber-600' },
            { label: 'Excused', value: session.excused, cls: 'text-blue-600' },
            { label: 'Absent',  value: session.absent,  cls: 'text-red-600' },
          ].map(({ label, value, cls }) => (
            <div key={label} className="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
              <p className={`text-2xl font-semibold ${cls}`}>{value}</p>
              <p className="text-xs text-gray-400 uppercase tracking-wider">{label}</p>
            </div>
          ))}
        </div>

        <div className="flex items-center justify-between">
          <p className="text-sm text-gray-500">Recorded by {session.teacher_name ?? '—'}</p>
          <Link href={editUrl} className="text-sm text-indigo-500 hover:text-indigo-600 font-medium">Edit attendance</Link>
        </div>

        <div className="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-100 text-left bg-gray-50">
                <th className="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Student</th>
                <th className="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Status</th>
                <th className="px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Note</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50">
              {session.records.map((r, i) => (
                <tr key={i}>
                  <td className="px-5 py-3">
                    <p className="font-medium text-gray-900">{r.student_name ?? '—'}</p>
                    <p className="text-xs text-gray-400 font-mono">{r.student_uid}</p>
                  </td>
                  <td className="px-5 py-3">
                    <span className={`text-xs px-2 py-0.5 rounded-full capitalize ${STATUS_COLORS[r.status] ?? 'bg-gray-100 text-gray-500'}`}>{r.status}</span>
                  </td>
                  <td className="px-5 py-3 text-gray-500">{r.note ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <Link href="/school/attendance" className="inline-block text-sm text-gray-500 hover:text-gray-700">← Back to Attendance</Link>
      </div>
    </SchoolLayout>
  );
}
