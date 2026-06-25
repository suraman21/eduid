import React, { useState } from 'react';
import { Users, CheckCircle2, AlertTriangle, XCircle, Search, Plus, QrCode, Send, RefreshCw, X, Trash2, CreditCard, ShieldCheck } from 'lucide-react';

interface Student {
  id: string;
  name: string;
  role: string;
  status: 'Present' | 'Late' | 'Absent';
  scannedAt: string;
  phone: string;
  email: string;
}

interface Props {
  onClose: () => void;
}

export default function AdminPanel({ onClose }: Props) {
  const [students, setStudents] = useState<Student[]>([
    { id: '2024-ED8821', name: 'Sarah Jenkins', role: 'STUDENT', status: 'Present', scannedAt: '08:14 AM', phone: '+251 911 231 988', email: 'sarah.j@school.edu.et' },
    { id: '2024-ED5412', name: 'Marcus Sterling', role: 'STUDENT', status: 'Present', scannedAt: '08:02 AM', phone: '+251 911 432 114', email: 'marcus.s@school.edu.et' },
    { id: '2024-ED9803', name: 'Elena Rostova', role: 'STUDENT', status: 'Late', scannedAt: '08:42 AM', phone: '+251 911 876 554', email: 'elena.r@school.edu.et' },
    { id: '2024-ED3302', name: 'David Cho', role: 'STUDENT', status: 'Absent', scannedAt: '—', phone: '+251 911 901 223', email: 'david.c@school.edu.et' },
    { id: '2024-ST1024', name: 'Dr. Evelyn Foster', role: 'FACULTY', status: 'Present', scannedAt: '07:45 AM', phone: '+251 911 123 456', email: 'e.foster@school.edu.et' },
    { id: '2024-ST9912', name: 'Coach Robert Miller', role: 'STAFF', status: 'Present', scannedAt: '07:30 AM', phone: '+251 911 765 432', email: 'r.miller@school.edu.et' },
  ]);

  const [searchTerm, setSearchTerm] = useState('');
  const [filterStatus, setFilterStatus] = useState('All');
  const [newName, setNewName] = useState('');
  const [newRole, setNewRole] = useState('STUDENT');
  const [newPhone, setNewPhone] = useState('');
  const [newEmail, setNewEmail] = useState('');
  const [scanFeed, setScanFeed] = useState<string[]>([
    'System initialization... Secure server connection established.',
    'Gateway scan terminal #4 (Main Gate) is ONLINE.',
  ]);
  const [smsLog, setSmsLog] = useState<string[]>([]);

  const totalCards = students.length;
  const presentCount = students.filter(s => s.status === 'Present').length;
  const lateCount = students.filter(s => s.status === 'Late').length;
  const absentCount = students.filter(s => s.status === 'Absent').length;

  const handleCreateStudent = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newName) return;
    const newId = `2024-ED${Math.floor(1000 + Math.random() * 9000)}`;
    const student: Student = { id: newId, name: newName, role: newRole, status: 'Absent', scannedAt: '—', phone: newPhone || '+251 911 000 000', email: newEmail || `${newName.toLowerCase().replace(/\s+/g, '.')}@school.edu.et` };
    setStudents([student, ...students]);
    setNewName(''); setNewPhone(''); setNewEmail('');
    setScanFeed(prev => [`[REGISTRATION] Issued new ID card ${newId} for ${newName} (${newRole})`, ...prev]);
  };

  const handleSimulateScan = () => {
    const absents = students.filter(s => s.status === 'Absent');
    const target = absents.length > 0 ? absents[Math.floor(Math.random() * absents.length)] : students[Math.floor(Math.random() * students.length)];
    const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    setStudents(prev => prev.map(s => s.id === target.id ? { ...s, status: 'Present', scannedAt: timeStr } : s));
    setScanFeed(prev => [`[TAP IN] ${target.name} (${target.role}) scanned ID ${target.id} at Main Entrance. OK.`, ...prev]);
    setSmsLog(prev => [`SMS to parent (${target.phone}): "EduID: ${target.name} checked in at ${timeStr}."`, ...prev]);
  };

  const handleDelete = (id: string) => {
    setStudents(students.filter(s => s.id !== id));
    setScanFeed(prev => [`[REVOKED] ID token for card ${id} has been blacklisted.`, ...prev]);
  };

  const filtered = students.filter(s =>
    (s.name.toLowerCase().includes(searchTerm.toLowerCase()) || s.id.includes(searchTerm)) &&
    (filterStatus === 'All' || s.status === filterStatus)
  );

  return (
    <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4 sm:p-6 overflow-y-auto">
      <div className="bg-[#F8FAFC] w-full max-w-6xl rounded-3xl shadow-2xl overflow-hidden border border-slate-200 flex flex-col max-h-[90vh] animate-fadeIn">

        <div className="bg-primary text-white p-6 flex justify-between items-center border-b border-slate-800">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-secondary rounded-lg"><ShieldCheck className="w-6 h-6 text-white" /></div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="font-display font-extrabold text-lg sm:text-xl tracking-tight">EduID Administrative Suite</h3>
                <span className="bg-emerald-500/10 border border-emerald-400/20 text-emerald-400 text-[10px] font-bold px-2 py-0.5 rounded uppercase font-mono">Admin Active</span>
              </div>
              <p className="text-xs text-slate-400 font-sans">Complete dashboard controls for card issuing & live check-ins</p>
            </div>
          </div>
          <button onClick={onClose} className="p-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors">
            <X className="w-6 h-6" />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto p-6 sm:p-8 space-y-8">

          {/* Stats */}
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            {[
              { label: 'Total Issued ID Cards', value: totalCards, icon: Users, bg: 'bg-primary/10', color: 'text-primary' },
              { label: 'Present (In School)', value: presentCount, icon: CheckCircle2, bg: 'bg-secondary/10', color: 'text-secondary' },
              { label: 'Late Check-ins', value: lateCount, icon: AlertTriangle, bg: 'bg-amber-100', color: 'text-amber-600' },
              { label: 'Absent / Unmarked', value: absentCount, icon: XCircle, bg: 'bg-rose-100', color: 'text-rose-600' },
            ].map(({ label, value, icon: Icon, bg, color }) => (
              <div key={label} className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
                <div className={`w-12 h-12 rounded-xl ${bg} flex items-center justify-center ${color}`}><Icon className="w-6 h-6" /></div>
                <div>
                  <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">{label}</p>
                  <p className={`text-2xl font-black font-display ${color}`}>{value}</p>
                </div>
              </div>
            ))}
          </div>

          {/* Main panels */}
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">

            {/* Scanner simulator */}
            <div className="lg:col-span-5 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between gap-6">
              <div>
                <div className="flex items-center justify-between mb-4">
                  <h4 className="font-display font-extrabold text-sm sm:text-base text-primary flex items-center gap-2">
                    <QrCode className="w-5 h-5 text-secondary" /> Scan Hardware Simulator
                  </h4>
                  <span className="flex h-2.5 w-2.5 relative">
                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
                    <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500" />
                  </span>
                </div>
                <p className="text-xs text-slate-500 mb-6 leading-relaxed">Simulate physical card check-ins. Click below to broadcast a secure scan event from student hardware gates.</p>
                <div className="bg-[#0B132B] font-mono text-[11px] p-4 rounded-xl text-emerald-400 h-44 overflow-y-auto space-y-2 border border-slate-800 shadow-inner">
                  {scanFeed.map((log, i) => (
                    <p key={i} className="leading-relaxed"><span className="text-slate-500">➜</span> {log}</p>
                  ))}
                </div>
              </div>
              <div className="space-y-3 pt-4 border-t border-slate-100">
                <button onClick={handleSimulateScan} className="w-full bg-secondary hover:brightness-110 text-white font-bold text-xs py-3 rounded-lg shadow-md transition-all flex items-center justify-center gap-2">
                  <RefreshCw className="w-4 h-4 animate-spin-slow" /> Simulate Instant Card Tap
                </button>
                <div className="bg-slate-50 border border-slate-200 p-3 rounded-lg">
                  <p className="text-[9px] font-bold uppercase tracking-wider text-slate-400 mb-1.5 flex items-center gap-1">
                    <Send className="w-3 h-3 text-secondary" /> Dynamic Parent SMS logs:
                  </p>
                  {smsLog.length === 0
                    ? <p className="text-[10px] text-slate-400 italic">No SMS broadcasts yet. Tap a student card above!</p>
                    : <div className="max-h-16 overflow-y-auto space-y-1">{smsLog.map((sms, i) => <p key={i} className="text-[10px] text-slate-600 font-medium font-sans truncate">{sms}</p>)}</div>
                  }
                </div>
              </div>
            </div>

            {/* Issue card form */}
            <div className="lg:col-span-7 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
              <h4 className="font-display font-extrabold text-sm sm:text-base text-primary flex items-center gap-2 mb-4">
                <CreditCard className="w-5 h-5 text-secondary" /> Issue New Smart ID Card (PVC format)
              </h4>
              <form onSubmit={handleCreateStudent} className="space-y-4">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div className="space-y-1">
                    <label className="block text-[10px] font-bold text-slate-500 uppercase">Student Name</label>
                    <input type="text" required value={newName} onChange={e => setNewName(e.target.value)} className="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary" placeholder="e.g. Abebe Girma" />
                  </div>
                  <div className="space-y-1">
                    <label className="block text-[10px] font-bold text-slate-500 uppercase">Role Type</label>
                    <select value={newRole} onChange={e => setNewRole(e.target.value)} className="w-full text-xs px-2.5 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary">
                      <option value="STUDENT">Student</option>
                      <option value="FACULTY">Faculty</option>
                      <option value="STAFF">Staff</option>
                      <option value="VISITOR">Visitor</option>
                    </select>
                  </div>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div className="space-y-1">
                    <label className="block text-[10px] font-bold text-slate-500 uppercase">Parent Phone</label>
                    <input type="tel" value={newPhone} onChange={e => setNewPhone(e.target.value)} className="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary" placeholder="+251 911 000 000" />
                  </div>
                  <div className="space-y-1">
                    <label className="block text-[10px] font-bold text-slate-500 uppercase">Academic Email</label>
                    <input type="email" value={newEmail} onChange={e => setNewEmail(e.target.value)} className="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary" placeholder="student@school.edu.et" />
                  </div>
                </div>
                <button type="submit" className="w-full bg-primary hover:bg-slate-800 text-white font-bold text-xs py-3 rounded-lg shadow transition-all flex items-center justify-center gap-2">
                  <Plus className="w-4 h-4" /> Approve & Print Card Token
                </button>
              </form>
            </div>
          </div>

          {/* Student directory */}
          <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div className="p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
              <div>
                <h4 className="font-display font-extrabold text-sm sm:text-base text-primary">Registered Students & Staff Directory</h4>
                <p className="text-xs text-slate-500 font-sans mt-0.5">Filter by status, search by name or ID, and revoke access privileges.</p>
              </div>
              <div className="flex flex-col sm:flex-row gap-3">
                <div className="relative">
                  <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                  <input type="text" value={searchTerm} onChange={e => setSearchTerm(e.target.value)} className="pl-9 pr-4 py-2 text-xs border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary w-full sm:w-48" placeholder="Search name or ID" />
                </div>
                <div className="flex gap-2 items-center bg-slate-50 border border-slate-200 rounded-lg p-1">
                  {['All', 'Present', 'Late', 'Absent'].map(status => (
                    <button key={status} onClick={() => setFilterStatus(status)} className={`px-2.5 py-1 text-[10px] font-bold rounded ${filterStatus === status ? 'bg-white shadow-sm text-primary' : 'text-slate-500 hover:text-primary'}`}>{status}</button>
                  ))}
                </div>
              </div>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse text-xs">
                <thead>
                  <tr className="bg-slate-50 text-slate-500 font-bold uppercase text-[10px] border-b border-slate-200">
                    <th className="px-6 py-4">ID Reference</th>
                    <th className="px-6 py-4">Name</th>
                    <th className="px-6 py-4">Role</th>
                    <th className="px-6 py-4">Status</th>
                    <th className="px-6 py-4">Scanned At</th>
                    <th className="px-6 py-4">Contacts</th>
                    <th className="px-6 py-4 text-right">Action</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 font-medium">
                  {filtered.length === 0 ? (
                    <tr><td colSpan={7} className="px-6 py-10 text-center text-slate-400 italic">No students found matching current filters.</td></tr>
                  ) : filtered.map(student => (
                    <tr key={student.id} className="hover:bg-slate-50/50 transition-colors">
                      <td className="px-6 py-4 font-mono font-bold text-slate-400">{student.id}</td>
                      <td className="px-6 py-4 text-primary font-display font-extrabold">{student.name}</td>
                      <td className="px-6 py-4">
                        <span className={`px-2 py-0.5 rounded text-[9px] font-bold tracking-wide uppercase ${student.role === 'STUDENT' ? 'bg-indigo-50 text-indigo-700' : student.role === 'FACULTY' ? 'bg-purple-50 text-purple-700' : 'bg-slate-100 text-slate-700'}`}>{student.role}</span>
                      </td>
                      <td className="px-6 py-4">
                        <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold ${student.status === 'Present' ? 'bg-emerald-100 text-emerald-800' : student.status === 'Late' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800'}`}>
                          <span className={`w-1.5 h-1.5 rounded-full ${student.status === 'Present' ? 'bg-emerald-500' : student.status === 'Late' ? 'bg-amber-500' : 'bg-rose-500'}`} />
                          {student.status}
                        </span>
                      </td>
                      <td className="px-6 py-4 font-mono text-slate-500">{student.scannedAt}</td>
                      <td className="px-6 py-4">
                        <div className="space-y-0.5 text-slate-500">
                          <p className="text-[10px] text-slate-400 leading-tight">{student.email}</p>
                          <p className="text-[10px] text-slate-400 font-mono leading-tight">{student.phone}</p>
                        </div>
                      </td>
                      <td className="px-6 py-4 text-right">
                        <button onClick={() => handleDelete(student.id)} className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors inline-flex items-center gap-1 font-bold text-[10px]">
                          <Trash2 className="w-3.5 h-3.5" /> Revoke ID
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

        </div>

        <div className="bg-slate-100 p-4 border-t border-slate-200 text-center flex flex-col sm:flex-row justify-between items-center text-[10px] text-slate-400 font-medium">
          <p>EduID Gateways: 4 active hardware terminals recorded.</p>
          <div className="flex gap-4 mt-2 sm:mt-0">
            <span>Terminal Version: 2.4.0</span>
            <span>Secure Protocol: TLS 1.3 Active</span>
          </div>
        </div>

      </div>
    </div>
  );
}
