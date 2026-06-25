import { Head, Link, useForm, usePage } from '@inertiajs/react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';

interface SchoolClass { id: number; display_name: string; }
interface Props { classes: SchoolClass[]; }

export default function PhotoSessionCreate({ classes }: Props) {
  const { props } = usePage<PageProps>();
  const flash = props.flash;
  const { data, setData, post, processing, errors } = useForm({ class_id: '', session_type: 'app_capture' });

  const submit = (e: React.FormEvent) => { e.preventDefault(); post('/school/photo-sessions'); };

  return (
    <SchoolLayout>
      <Head title="New Photo Session" />

      {flash?.error && <div className="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{flash.error}</div>}

      <div className="max-w-xl">
        <form onSubmit={submit}>
          <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-6">

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Class <span className="text-gray-400 font-normal">(optional — leave blank for whole school)</span>
              </label>
              <select value={data.class_id} onChange={e => setData('class_id', e.target.value)}
                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 bg-white focus:outline-none focus:border-indigo-400">
                <option value="">All classes (whole school)</option>
                {classes.map(c => <option key={c.id} value={c.id}>{c.display_name}</option>)}
              </select>
              {errors.class_id && <p className="mt-1 text-xs text-red-600">{errors.class_id}</p>}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-3">Session Type</label>
              <div className="grid grid-cols-2 gap-3">
                {[
                  {
                    value: 'app_capture',
                    title: 'Step-by-step',
                    desc: 'Upload one photo per student in queue order. Best for picture day.',
                    icon: <><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></>
                  },
                  {
                    value: 'zip_upload',
                    title: 'ZIP Upload',
                    desc: 'Upload a ZIP of sequentially-named files (001.jpg, 002.jpg…). Best for studio photos.',
                    icon: <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                  },
                ].map(opt => (
                  <label key={opt.value} className="relative cursor-pointer">
                    <input type="radio" className="peer sr-only" value={opt.value} checked={data.session_type === opt.value}
                      onChange={() => setData('session_type', opt.value)} />
                    <div className="border-2 border-gray-200 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 rounded-xl p-4 transition">
                      <div className="flex items-center gap-2 mb-2">
                        <svg className="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">{opt.icon}</svg>
                        <span className="text-sm font-semibold text-gray-800">{opt.title}</span>
                      </div>
                      <p className="text-xs text-gray-500">{opt.desc}</p>
                    </div>
                  </label>
                ))}
              </div>
              {errors.session_type && <p className="mt-1 text-xs text-red-600">{errors.session_type}</p>}
            </div>

            <div className="bg-blue-50 border border-blue-100 rounded-lg p-4 text-xs text-blue-700">
              <strong>How it works:</strong> The system will generate an alphabetical queue of active students.
              For step-by-step, line up students in that order and upload each photo as they step forward.
              For ZIP upload, name your files 001.jpg, 002.jpg, etc. matching the alphabetical order.
            </div>
          </div>

          <div className="mt-4 flex items-center gap-3">
            <button type="submit" disabled={processing}
              className="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition disabled:opacity-60">
              {processing ? 'Starting…' : 'Start Session'}
            </button>
            <Link href="/school/photo-sessions" className="text-sm text-gray-400 hover:text-gray-600">Cancel</Link>
          </div>
        </form>
      </div>
    </SchoolLayout>
  );
}
