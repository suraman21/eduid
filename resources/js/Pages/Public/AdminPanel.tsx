import React, { useState, useEffect } from 'react';
import {
  Users, CheckCircle2, Package, ScanLine, X, Plus, Search,
  LayoutDashboard, Smartphone, BarChart3, AlertCircle, Check, Trash2
} from 'lucide-react';
import QRCode from 'qrcode';

interface Props {
  onClose: () => void;
}

// ─── types ────────────────────────────────────────────────────────────────────

interface DemoStudent {
  uid: string;
  name: string;
  nameAm: string;
  grade: string;
  dob: string;
  blood: string;
  attendanceStatus: 'not-taken' | 'present' | 'absent' | 'late';
  scannedAt: string | null;
}

interface CardOrder {
  code: string;
  studentCount: number;
  status: 'Pending' | 'In Print' | 'Shipped' | 'Delivered';
  placedAt: string;
}

// ─── helpers ──────────────────────────────────────────────────────────────────

const SCHOOL = { name: 'Abyssinia International Academy', code: 'AIA', year: '2025/26', accent: '#059669' };

function genUID(code: string) {
  const n = Math.floor(100 + Math.random() * 900);
  const y = new Date().getFullYear();
  return `${code}-${y}-0${n}`;
}

function genOrderCode(code: string) {
  const n = Math.floor(1000 + Math.random() * 9000);
  const y = new Date().getFullYear();
  return `ORD-${code}-${y}-${n}`;
}

const SEED_STUDENTS: DemoStudent[] = [
  { uid: 'AIA-2026-00142', name: 'Abebe Girma',   nameAm: 'አበበ ግርማ',   grade: 'Grade 10 – A', dob: '2010-04-14', blood: 'O+',  attendanceStatus: 'present', scannedAt: '08:12' },
  { uid: 'AIA-2026-00215', name: 'Tigist Bekele',  nameAm: 'ትግስት በቀለ',  grade: 'Grade 10 – A', dob: '2010-08-22', blood: 'A+',  attendanceStatus: 'present', scannedAt: '08:09' },
  { uid: 'AIA-2026-00388', name: 'Yohannes Alemu', nameAm: 'ዮሃንስ አለሙ',  grade: 'Grade 10 – B', dob: '2009-12-05', blood: 'B+',  attendanceStatus: 'late',    scannedAt: '08:47' },
  { uid: 'AIA-2026-00401', name: 'Selam Haile',    nameAm: 'ሰላም ሃይሌ',   grade: 'Grade 10 – B', dob: '2010-02-18', blood: 'AB+', attendanceStatus: 'absent',  scannedAt: null },
  { uid: 'AIA-2026-00512', name: 'Dawit Tesfaye',  nameAm: 'ዳዊት ተስፋዬ',  grade: 'Grade 9 – A',  dob: '2011-06-30', blood: 'O−',  attendanceStatus: 'not-taken', scannedAt: null },
];

const SEED_ORDERS: CardOrder[] = [
  { code: 'ORD-AIA-2026-0847', studentCount: 4, status: 'Shipped',  placedAt: '2026-06-20' },
  { code: 'ORD-AIA-2026-0612', studentCount: 2, status: 'Delivered', placedAt: '2026-05-15' },
];

// ─── QR mini component ────────────────────────────────────────────────────────

function MiniQR({ uid, size = 44 }: { uid: string; size?: number }) {
  const [svg, setSvg] = useState('');
  useEffect(() => {
    const payload = `${uid}|DEMO-TOKEN-${uid}`;
    QRCode.toString(payload, { type: 'svg', width: size, margin: 0, color: { dark: '#111827', light: '#ffffff' } })
      .then(setSvg).catch(() => {});
  }, [uid, size]);
  if (!svg) return <div style={{ width: size, height: size, background: '#f3f4f6', borderRadius: 4 }} />;
  return <div style={{ width: size, height: size }} dangerouslySetInnerHTML={{ __html: svg }} />;
}

// ─── tabs ─────────────────────────────────────────────────────────────────────

type Tab = 'dashboard' | 'students' | 'orders' | 'attendance';

// ─── main component ───────────────────────────────────────────────────────────

export default function AdminPanel({ onClose }: Props) {
  const [tab, setTab] = useState<Tab>('dashboard');
  const [students, setStudents] = useState<DemoStudent[]>(SEED_STUDENTS);
  const [orders, setOrders] = useState<CardOrder[]>(SEED_ORDERS);

  // student form
  const [newName, setNewName] = useState('');
  const [newNameAm, setNewNameAm] = useState('');
  const [newGrade, setNewGrade] = useState('Grade 10 – A');
  const [newDob, setNewDob] = useState('');
  const [newBlood, setNewBlood] = useState('O+');
  const [addedUID, setAddedUID] = useState<string | null>(null);

  // search
  const [search, setSearch] = useState('');

  // order
  const [selectedForOrder, setSelectedForOrder] = useState<Set<string>>(new Set());
  const [lastOrder, setLastOrder] = useState<CardOrder | null>(null);

  // attendance
  const [sessionGrade, setSessionGrade] = useState('Grade 10 – A');
  const [scanningUID, setScanningUID] = useState<string | null>(null); // shows QR "flash"
  const [attendanceLog, setAttendanceLog] = useState<string[]>([
    'Teacher app connected. Attendance session ready.',
  ]);

  const totalStudents = students.length;
  const totalOrdered = orders.reduce((s, o) => s + o.studentCount, 0);
  const totalPresent = students.filter(s => s.attendanceStatus === 'present').length;
  const totalAbsent = students.filter(s => s.attendanceStatus === 'absent').length;

  const grades = [...new Set(students.map(s => s.grade))].sort();
  const sessionStudents = students.filter(s => s.grade === sessionGrade);

  // ── handlers ────────────────────────────────────────────────────────────────

  function handleAddStudent(e: React.FormEvent) {
    e.preventDefault();
    if (!newName.trim()) return;
    const uid = genUID(SCHOOL.code);
    const student: DemoStudent = {
      uid, name: newName.trim(), nameAm: newNameAm.trim(),
      grade: newGrade, dob: newDob, blood: newBlood,
      attendanceStatus: 'not-taken', scannedAt: null,
    };
    setStudents(prev => [student, ...prev]);
    setAddedUID(uid);
    setNewName(''); setNewNameAm(''); setNewDob('');
    setTimeout(() => setAddedUID(null), 4000);
  }

  function handleRemoveStudent(uid: string) {
    setStudents(prev => prev.filter(s => s.uid !== uid));
    setSelectedForOrder(prev => { const n = new Set(prev); n.delete(uid); return n; });
  }

  function toggleOrderSelect(uid: string) {
    setSelectedForOrder(prev => {
      const n = new Set(prev);
      n.has(uid) ? n.delete(uid) : n.add(uid);
      return n;
    });
  }

  function handlePlaceOrder() {
    if (selectedForOrder.size === 0) return;
    const order: CardOrder = {
      code: genOrderCode(SCHOOL.code),
      studentCount: selectedForOrder.size,
      status: 'Pending',
      placedAt: new Date().toISOString().slice(0, 10),
    };
    setOrders(prev => [order, ...prev]);
    setLastOrder(order);
    setSelectedForOrder(new Set());
  }

  function handleScan(student: DemoStudent) {
    setScanningUID(student.uid);
    const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    setTimeout(() => {
      setStudents(prev => prev.map(s =>
        s.uid === student.uid ? { ...s, attendanceStatus: 'present', scannedAt: time } : s
      ));
      setAttendanceLog(prev => [
        `✓ ${student.name} (${student.uid}) — present at ${time}`,
        ...prev,
      ]);
      setScanningUID(null);
    }, 900);
  }

  function handleMarkAbsent(student: DemoStudent) {
    setStudents(prev => prev.map(s =>
      s.uid === student.uid ? { ...s, attendanceStatus: 'absent', scannedAt: null } : s
    ));
    setAttendanceLog(prev => [`✗ ${student.name} marked absent`, ...prev]);
  }

  // ── filtered students ────────────────────────────────────────────────────────

  const filtered = students.filter(s =>
    s.name.toLowerCase().includes(search.toLowerCase()) ||
    s.uid.toLowerCase().includes(search.toLowerCase()) ||
    s.grade.toLowerCase().includes(search.toLowerCase())
  );

  // ── tabs config ──────────────────────────────────────────────────────────────

  const tabs: { id: Tab; label: string; icon: React.ElementType }[] = [
    { id: 'dashboard',  label: 'Dashboard',   icon: LayoutDashboard },
    { id: 'students',   label: 'Students',    icon: Users },
    { id: 'orders',     label: 'Card Orders', icon: Package },
    { id: 'attendance', label: 'Attendance',  icon: ScanLine },
  ];

  return (
    <div className="fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-md flex items-center justify-center p-3 sm:p-6 overflow-y-auto">
      <div className="bg-[#F8FAFC] w-full max-w-5xl rounded-3xl shadow-2xl overflow-hidden border border-slate-200 flex flex-col max-h-[92vh]">

        {/* Header */}
        <div className="bg-primary text-white px-6 py-4 flex items-center justify-between border-b border-slate-800 flex-shrink-0">
          <div>
            <div className="flex items-center gap-2">
              <span className="font-display font-extrabold text-base sm:text-lg tracking-tight">EduID — Live Sandbox Demo</span>
              <span className="bg-emerald-400/15 border border-emerald-400/20 text-emerald-400 text-[9px] font-bold px-2 py-0.5 rounded uppercase">Demo</span>
            </div>
            <p className="text-slate-400 text-[11px] mt-0.5">{SCHOOL.name} · {SCHOOL.year}</p>
          </div>
          <button onClick={onClose} className="p-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors">
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Tabs */}
        <div className="flex border-b border-slate-200 bg-white flex-shrink-0 overflow-x-auto">
          {tabs.map(t => {
            const Icon = t.icon;
            return (
              <button
                key={t.id}
                onClick={() => setTab(t.id)}
                className={`flex items-center gap-1.5 px-4 sm:px-5 py-3.5 text-xs font-bold whitespace-nowrap transition-all border-b-2 ${tab === t.id ? 'border-secondary text-secondary' : 'border-transparent text-slate-500 hover:text-primary'}`}
              >
                <Icon className="w-3.5 h-3.5" />
                {t.label}
              </button>
            );
          })}
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto">

          {/* ── DASHBOARD ── */}
          {tab === 'dashboard' && (
            <div className="p-6 space-y-6">
              <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                {[
                  { label: 'Students Registered', value: totalStudents, icon: Users, color: 'text-primary', bg: 'bg-primary/10' },
                  { label: 'Cards Ordered (total)', value: totalOrdered, icon: Package, color: 'text-indigo-600', bg: 'bg-indigo-50' },
                  { label: 'Present Today', value: totalPresent, icon: CheckCircle2, color: 'text-secondary', bg: 'bg-secondary/10' },
                  { label: 'Absent / Not Taken', value: totalAbsent + students.filter(s => s.attendanceStatus === 'not-taken').length, icon: AlertCircle, color: 'text-rose-600', bg: 'bg-rose-50' },
                ].map(({ label, value, icon: Icon, color, bg }) => (
                  <div key={label} className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
                    <div className={`w-11 h-11 rounded-xl ${bg} flex items-center justify-center ${color} flex-shrink-0`}>
                      <Icon className="w-5 h-5" />
                    </div>
                    <div>
                      <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400 leading-tight">{label}</p>
                      <p className={`text-2xl font-black font-display ${color}`}>{value}</p>
                    </div>
                  </div>
                ))}
              </div>

              {/* Recent orders */}
              <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h4 className="font-display font-extrabold text-sm text-primary mb-4 flex items-center gap-2">
                  <Package className="w-4 h-4 text-secondary" /> Recent Card Orders
                </h4>
                <div className="space-y-2">
                  {orders.slice(0, 3).map(o => (
                    <div key={o.code} className="flex items-center justify-between py-2 border-b border-slate-50 last:border-0 text-xs">
                      <div>
                        <p className="font-mono font-bold text-slate-700">{o.code}</p>
                        <p className="text-slate-400 text-[11px]">{o.studentCount} cards · {o.placedAt}</p>
                      </div>
                      <span className={`px-2.5 py-0.5 rounded-full text-[10px] font-bold ${
                        o.status === 'Delivered' ? 'bg-emerald-100 text-emerald-700' :
                        o.status === 'Shipped'   ? 'bg-indigo-100 text-indigo-700' :
                        o.status === 'In Print'  ? 'bg-amber-100 text-amber-700' :
                                                   'bg-slate-100 text-slate-600'
                      }`}>{o.status}</span>
                    </div>
                  ))}
                  {orders.length === 0 && <p className="text-xs text-slate-400 italic">No orders yet. Go to Card Orders to place one.</p>}
                </div>
              </div>

              {/* Attendance snapshot */}
              <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h4 className="font-display font-extrabold text-sm text-primary mb-4 flex items-center gap-2">
                  <BarChart3 className="w-4 h-4 text-secondary" /> Today's Attendance Snapshot
                </h4>
                <div className="space-y-2">
                  {grades.map(grade => {
                    const gs = students.filter(s => s.grade === grade);
                    const present = gs.filter(s => s.attendanceStatus === 'present').length;
                    const pct = gs.length ? Math.round((present / gs.length) * 100) : 0;
                    return (
                      <div key={grade} className="flex items-center gap-3 text-xs">
                        <span className="w-32 text-slate-600 font-medium truncate flex-shrink-0">{grade}</span>
                        <div className="flex-1 bg-slate-100 rounded-full h-2">
                          <div className="bg-secondary rounded-full h-2 transition-all duration-500" style={{ width: `${pct}%` }} />
                        </div>
                        <span className="text-slate-500 font-mono w-20 text-right">{present}/{gs.length} ({pct}%)</span>
                      </div>
                    );
                  })}
                </div>
              </div>
            </div>
          )}

          {/* ── STUDENTS ── */}
          {tab === 'students' && (
            <div className="p-6 space-y-5">
              {/* Add student form */}
              <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h4 className="font-display font-extrabold text-sm text-primary mb-4 flex items-center gap-2">
                  <Plus className="w-4 h-4 text-secondary" /> Add New Student
                </h4>
                <form onSubmit={handleAddStudent} className="space-y-3">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                      <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Full Name *</label>
                      <input required type="text" value={newName} onChange={e => setNewName(e.target.value)} className="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary" placeholder="e.g. Abebe Girma" />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Amharic Name</label>
                      <input type="text" value={newNameAm} onChange={e => setNewNameAm(e.target.value)} className="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary" placeholder="e.g. አበበ ግርማ" />
                    </div>
                  </div>
                  <div className="grid grid-cols-3 gap-3">
                    <div>
                      <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Grade / Class</label>
                      <select value={newGrade} onChange={e => setNewGrade(e.target.value)} className="w-full text-xs px-2 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary">
                        {['Grade 9 – A','Grade 9 – B','Grade 10 – A','Grade 10 – B','Grade 11 – A','Grade 12 – A'].map(g => <option key={g}>{g}</option>)}
                      </select>
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Date of Birth</label>
                      <input type="date" value={newDob} onChange={e => setNewDob(e.target.value)} className="w-full text-xs px-2 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary" />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Blood Type</label>
                      <select value={newBlood} onChange={e => setNewBlood(e.target.value)} className="w-full text-xs px-2 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary">
                        {['A+','B+','O+','AB+','A−','B−','O−','AB−'].map(b => <option key={b}>{b}</option>)}
                      </select>
                    </div>
                  </div>
                  <button type="submit" className="w-full bg-primary hover:bg-slate-800 text-white text-xs font-bold py-2.5 rounded-lg transition-all flex items-center justify-center gap-2">
                    <Plus className="w-3.5 h-3.5" /> Register Student & Assign UID
                  </button>
                </form>
                {addedUID && (
                  <div className="mt-3 p-3 bg-emerald-50 border border-emerald-200 rounded-lg flex items-center gap-2 text-xs text-emerald-800">
                    <Check className="w-4 h-4 text-emerald-600 flex-shrink-0" />
                    <span>Student registered. Assigned UID: <strong className="font-mono">{addedUID}</strong></span>
                  </div>
                )}
              </div>

              {/* Directory */}
              <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div className="p-5 border-b border-slate-100 flex items-center justify-between gap-3">
                  <h4 className="font-display font-extrabold text-sm text-primary">Student Registry ({students.length})</h4>
                  <div className="relative">
                    <Search className="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" />
                    <input type="text" value={search} onChange={e => setSearch(e.target.value)} className="pl-8 pr-3 py-2 text-xs border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 w-44" placeholder="Search name or UID" />
                  </div>
                </div>
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs">
                    <thead>
                      <tr className="bg-slate-50 text-slate-500 font-bold uppercase text-[10px] border-b border-slate-200">
                        <th className="px-5 py-3">UID</th>
                        <th className="px-5 py-3">Name</th>
                        <th className="px-5 py-3">Grade</th>
                        <th className="px-5 py-3">Blood</th>
                        <th className="px-5 py-3">QR</th>
                        <th className="px-5 py-3 text-right">Action</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {filtered.length === 0 ? (
                        <tr><td colSpan={6} className="px-5 py-8 text-center text-slate-400 italic">No students found.</td></tr>
                      ) : filtered.map(s => (
                        <tr key={s.uid} className="hover:bg-slate-50/50">
                          <td className="px-5 py-3 font-mono font-bold text-slate-500 text-[11px]">{s.uid}</td>
                          <td className="px-5 py-3">
                            <p className="font-bold text-primary">{s.name}</p>
                            {s.nameAm && <p className="text-slate-400 text-[11px]">{s.nameAm}</p>}
                          </td>
                          <td className="px-5 py-3 text-slate-600">{s.grade}</td>
                          <td className="px-5 py-3">
                            <span className="px-2 py-0.5 bg-red-50 text-red-700 rounded text-[10px] font-bold">{s.blood}</span>
                          </td>
                          <td className="px-5 py-3">
                            <MiniQR uid={s.uid} size={36} />
                          </td>
                          <td className="px-5 py-3 text-right">
                            <button onClick={() => handleRemoveStudent(s.uid)} className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors inline-flex items-center gap-1 text-[10px] font-bold">
                              <Trash2 className="w-3 h-3" /> Remove
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          )}

          {/* ── CARD ORDERS ── */}
          {tab === 'orders' && (
            <div className="p-6 space-y-5">
              {lastOrder && (
                <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-start gap-3">
                  <Check className="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" />
                  <div>
                    <p className="text-sm font-bold text-emerald-800">Order placed successfully!</p>
                    <p className="text-xs text-emerald-700 mt-0.5">
                      Order code: <strong className="font-mono">{lastOrder.code}</strong> · {lastOrder.studentCount} cards · Status: Pending
                    </p>
                    <p className="text-xs text-emerald-600 mt-1">Arkeon will print and ship your cards within 3 business days.</p>
                  </div>
                </div>
              )}

              {/* Select students for order */}
              <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h4 className="font-display font-extrabold text-sm text-primary mb-1 flex items-center gap-2">
                  <Package className="w-4 h-4 text-secondary" /> Place a Card Order
                </h4>
                <p className="text-xs text-slate-500 mb-4">Select students to include in this print batch. Arkeon prints PVC cards and ships them to your school (50 ETB/card).</p>
                <div className="space-y-2 max-h-64 overflow-y-auto">
                  {students.map(s => (
                    <label key={s.uid} className={`flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all ${selectedForOrder.has(s.uid) ? 'border-secondary bg-secondary/5' : 'border-slate-100 hover:bg-slate-50'}`}>
                      <input type="checkbox" checked={selectedForOrder.has(s.uid)} onChange={() => toggleOrderSelect(s.uid)} className="accent-secondary" />
                      <div className="flex-1 min-w-0">
                        <p className="text-xs font-bold text-primary truncate">{s.name}</p>
                        <p className="text-[10px] text-slate-400 font-mono">{s.uid} · {s.grade}</p>
                      </div>
                    </label>
                  ))}
                </div>
                {selectedForOrder.size > 0 && (
                  <div className="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between">
                    <div>
                      <p className="text-xs font-bold text-primary">{selectedForOrder.size} student{selectedForOrder.size > 1 ? 's' : ''} selected</p>
                      <p className="text-[11px] text-slate-500">Estimated cost: <strong>{(selectedForOrder.size * 50).toLocaleString()} ETB</strong></p>
                    </div>
                    <button onClick={handlePlaceOrder} className="bg-secondary text-white text-xs font-bold px-5 py-2.5 rounded-lg hover:brightness-110 transition-all">
                      Submit Order →
                    </button>
                  </div>
                )}
              </div>

              {/* Order history */}
              <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h4 className="font-display font-extrabold text-sm text-primary mb-4">Order History</h4>
                {orders.length === 0
                  ? <p className="text-xs text-slate-400 italic">No orders yet.</p>
                  : (
                    <div className="space-y-3">
                      {orders.map(o => (
                        <div key={o.code} className="flex items-center justify-between p-3 border border-slate-100 rounded-xl text-xs">
                          <div>
                            <p className="font-mono font-bold text-slate-700">{o.code}</p>
                            <p className="text-slate-400 text-[11px]">{o.studentCount} cards · placed {o.placedAt}</p>
                          </div>
                          <span className={`px-2.5 py-0.5 rounded-full text-[10px] font-bold ${
                            o.status === 'Delivered' ? 'bg-emerald-100 text-emerald-700' :
                            o.status === 'Shipped'   ? 'bg-indigo-100 text-indigo-700' :
                            o.status === 'In Print'  ? 'bg-amber-100 text-amber-700' :
                                                       'bg-slate-100 text-slate-600'
                          }`}>{o.status}</span>
                        </div>
                      ))}
                    </div>
                  )
                }
              </div>
            </div>
          )}

          {/* ── ATTENDANCE ── */}
          {tab === 'attendance' && (
            <div className="p-6 space-y-5">

              {/* Honest framing banner */}
              <div className="p-3.5 bg-slate-800 rounded-xl flex gap-3 items-start">
                <Smartphone className="w-5 h-5 text-secondary flex-shrink-0 mt-0.5" />
                <div>
                  <p className="text-xs font-bold text-white">This is what the EduID Flutter teacher app does</p>
                  <p className="text-[11px] text-slate-400 mt-0.5 leading-relaxed">
                    In real use, the teacher opens the app on their phone, selects the class, and scans each student's printed PVC card with the phone camera. The QR is read, the student is marked present, and the record syncs to this dashboard. Below is an interactive simulation of that flow.
                  </p>
                </div>
              </div>

              {/* Session controls */}
              <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <div className="flex items-center justify-between mb-4 flex-wrap gap-3">
                  <h4 className="font-display font-extrabold text-sm text-primary flex items-center gap-2">
                    <ScanLine className="w-4 h-4 text-secondary" /> Attendance Session
                  </h4>
                  <div className="flex items-center gap-2">
                    <label className="text-[10px] font-bold text-slate-500 uppercase">Class:</label>
                    <select value={sessionGrade} onChange={e => setSessionGrade(e.target.value)} className="text-xs px-2 py-1.5 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20">
                      {grades.map(g => <option key={g}>{g}</option>)}
                    </select>
                  </div>
                </div>

                <div className="space-y-2">
                  {sessionStudents.length === 0 && (
                    <p className="text-xs text-slate-400 italic py-4 text-center">No students in this class.</p>
                  )}
                  {sessionStudents.map(s => {
                    const isScanning = scanningUID === s.uid;
                    return (
                      <div
                        key={s.uid}
                        className={`flex items-center gap-4 p-3 rounded-xl border transition-all duration-300 ${
                          isScanning ? 'border-secondary bg-secondary/5 scale-[1.01]' :
                          s.attendanceStatus === 'present' ? 'border-emerald-200 bg-emerald-50/50' :
                          s.attendanceStatus === 'absent'  ? 'border-rose-100 bg-rose-50/30' :
                          'border-slate-100 hover:bg-slate-50'
                        }`}
                      >
                        {/* QR — lights up when scanning */}
                        <div className={`flex-shrink-0 transition-all duration-300 ${isScanning ? 'ring-2 ring-secondary ring-offset-1 rounded' : ''}`}>
                          <MiniQR uid={s.uid} size={40} />
                        </div>
                        <div className="flex-1 min-w-0">
                          <p className="text-xs font-bold text-primary truncate">{s.name}</p>
                          <p className="text-[10px] text-slate-400 font-mono">{s.uid}</p>
                        </div>
                        {/* Status badge */}
                        <div className="flex-shrink-0 flex items-center gap-2">
                          {isScanning ? (
                            <span className="text-[10px] font-bold text-secondary flex items-center gap-1">
                              <span className="w-2 h-2 rounded-full bg-secondary animate-ping" /> Reading QR…
                            </span>
                          ) : s.attendanceStatus === 'present' ? (
                            <span className="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded-full">
                              <Check className="w-3 h-3" /> Present {s.scannedAt && `· ${s.scannedAt}`}
                            </span>
                          ) : s.attendanceStatus === 'absent' ? (
                            <span className="px-2 py-0.5 bg-rose-100 text-rose-700 text-[10px] font-bold rounded-full">Absent</span>
                          ) : (
                            <div className="flex gap-1.5">
                              <button
                                onClick={() => handleScan(s)}
                                disabled={isScanning}
                                className="px-2.5 py-1 bg-secondary text-white text-[10px] font-bold rounded-lg hover:brightness-110 transition-all"
                              >
                                Scan Card
                              </button>
                              <button
                                onClick={() => handleMarkAbsent(s)}
                                className="px-2.5 py-1 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-lg hover:bg-slate-200 transition-all"
                              >
                                Absent
                              </button>
                            </div>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>

              {/* Attendance log */}
              <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h4 className="font-display font-extrabold text-xs text-slate-500 uppercase tracking-wider mb-3">Session Log (syncs to dashboard)</h4>
                <div className="bg-slate-900 font-mono text-[11px] p-4 rounded-xl h-36 overflow-y-auto space-y-1">
                  {attendanceLog.map((line, i) => (
                    <p key={i} className={i === 0 ? 'text-secondary' : 'text-slate-500'}>{line}</p>
                  ))}
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="bg-slate-100 px-6 py-3 border-t border-slate-200 flex items-center justify-between text-[10px] text-slate-400 font-medium flex-shrink-0">
          <span>EduID · Arkeon Ethiopia · {SCHOOL.year}</span>
          <span>Demo sandbox — no real data is stored</span>
        </div>
      </div>
    </div>
  );
}
