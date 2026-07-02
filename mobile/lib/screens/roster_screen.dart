import 'package:flutter/material.dart';

import '../models/models.dart';
import '../services/attendance_service.dart';
import '../services/data_service.dart';
import '../services/sync_engine.dart';
import '../ui/glass.dart';
import 'scanner_screen.dart';

/// Attendance capture: everyone defaults to PRESENT, P/A/L/E per student,
/// live search, bulk actions, QR scan → verified-present. Saving queues the
/// session and the SyncEngine pushes it automatically within a second.
class RosterScreen extends StatefulWidget {
  const RosterScreen({super.key, required this.schoolClass});

  final SchoolClass schoolClass;

  @override
  State<RosterScreen> createState() => _RosterScreenState();
}

class _RosterScreenState extends State<RosterScreen> {
  List<Student> _students = [];
  final Map<int, AttendanceStatus> _marks = {};
  final Set<int> _scanned = {};
  final _search = TextEditingController();
  String _sessionType = 'morning';
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _load();
    _search.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final students =
        await DataService.instance.cachedRoster(widget.schoolClass.id);
    if (!mounted) return;
    setState(() {
      _students = students;
      for (final s in students) {
        _marks[s.id] = AttendanceStatus.present;
      }
    });
  }

  List<Student> get _visible {
    final q = _search.text.trim().toLowerCase();
    if (q.isEmpty) return _students;
    return _students
        .where((s) =>
            s.fullName.toLowerCase().contains(q) ||
            s.uid.toLowerCase().contains(q))
        .toList();
  }

  Future<void> _scan() async {
    final student = await Navigator.of(context).push<Student>(
      MaterialPageRoute(
          builder: (_) => ScannerScreen(classId: widget.schoolClass.id)),
    );
    if (student != null && mounted) {
      setState(() {
        _marks[student.id] = AttendanceStatus.present;
        _scanned.add(student.id);
      });
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text('✓ ${student.fullName} verified & present'),
          duration: const Duration(seconds: 1)));
    }
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    await AttendanceService.instance.queueSession(
      schoolClass: widget.schoolClass,
      sessionType: _sessionType,
      marks: _marks,
    );
    // Automatic: the engine pushes this within a second when online.
    SyncEngine.instance.pushSoon();
    if (!mounted) return;
    setState(() => _saving = false);
    Navigator.of(context).pop();
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('Attendance saved — syncing automatically.')));
  }

  void _markAll(AttendanceStatus st) {
    setState(() {
      for (final s in _students) {
        _marks[s.id] = st;
      }
    });
  }

  Color _tone(AttendanceStatus s) => switch (s) {
        AttendanceStatus.present => Palette.emerald,
        AttendanceStatus.absent => Palette.rose,
        AttendanceStatus.late => Palette.amber,
        AttendanceStatus.excused => Palette.indigo,
      };

  @override
  Widget build(BuildContext context) {
    final present =
        _marks.values.where((s) => s == AttendanceStatus.present).length;
    final absent =
        _marks.values.where((s) => s == AttendanceStatus.absent).length;

    return AppBackground(
      child: Scaffold(
        backgroundColor: Colors.transparent,
        body: SafeArea(
          child: Column(
            children: [
              // ── Header ──
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
                child: Row(
                  children: [
                    GlassIconButton(
                        icon: Icons.arrow_back_rounded,
                        onTap: () => Navigator.of(context).pop()),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(widget.schoolClass.name,
                              style: const TextStyle(
                                  fontSize: 17,
                                  fontWeight: FontWeight.w800,
                                  color: Palette.textHi)),
                          Text('$present present · $absent absent',
                              style: const TextStyle(
                                  fontSize: 12, color: Palette.textLo)),
                        ],
                      ),
                    ),
                    // Session type pill
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10),
                      decoration: BoxDecoration(
                        color: Colors.white.withOpacity(0.07),
                        borderRadius: BorderRadius.circular(12),
                        border:
                            Border.all(color: Colors.white.withOpacity(0.12)),
                      ),
                      child: DropdownButton<String>(
                        value: _sessionType,
                        underline: const SizedBox.shrink(),
                        dropdownColor: Palette.bg2,
                        style: const TextStyle(
                            fontSize: 12.5, color: Palette.textMid),
                        items: const [
                          DropdownMenuItem(
                              value: 'morning', child: Text('Morning')),
                          DropdownMenuItem(
                              value: 'afternoon', child: Text('Afternoon')),
                          DropdownMenuItem(
                              value: 'full_day', child: Text('Full day')),
                        ],
                        onChanged: (v) =>
                            setState(() => _sessionType = v ?? 'morning'),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),

              // ── Search + bulk actions ──
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Row(
                  children: [
                    Expanded(
                      child: GlassField(
                        controller: _search,
                        label: 'Search name or ID…',
                        icon: Icons.search_rounded,
                      ),
                    ),
                    const SizedBox(width: 8),
                    GlassIconButton(
                      icon: Icons.done_all_rounded,
                      color: Palette.emerald,
                      tooltip: 'All present',
                      onTap: () => _markAll(AttendanceStatus.present),
                    ),
                    const SizedBox(width: 6),
                    GlassIconButton(
                      icon: Icons.remove_done_rounded,
                      color: Palette.rose,
                      tooltip: 'All absent',
                      onTap: () => _markAll(AttendanceStatus.absent),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 10),

              // ── Student list ──
              Expanded(
                child: ListView.builder(
                  padding: const EdgeInsets.fromLTRB(20, 4, 20, 8),
                  itemCount: _visible.length,
                  itemBuilder: (context, i) {
                    final s = _visible[i];
                    final mark = _marks[s.id] ?? AttendanceStatus.present;
                    return GlassCard(
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.symmetric(
                          horizontal: 14, vertical: 10),
                      radius: 18,
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(children: [
                                  Flexible(
                                    child: Text(s.fullName,
                                        overflow: TextOverflow.ellipsis,
                                        style: const TextStyle(
                                            fontWeight: FontWeight.w600,
                                            color: Palette.textHi,
                                            fontSize: 14)),
                                  ),
                                  if (_scanned.contains(s.id))
                                    const Padding(
                                      padding: EdgeInsets.only(left: 6),
                                      child: Icon(Icons.verified_rounded,
                                          size: 15, color: Palette.emerald),
                                    ),
                                ]),
                                Text(s.uid,
                                    style: const TextStyle(
                                        fontSize: 10.5,
                                        color: Palette.textLo)),
                              ],
                            ),
                          ),
                          for (final st in AttendanceStatus.values)
                            Padding(
                              padding: const EdgeInsets.only(left: 5),
                              child: GestureDetector(
                                onTap: () =>
                                    setState(() => _marks[s.id] = st),
                                child: AnimatedContainer(
                                  duration: const Duration(milliseconds: 160),
                                  width: 33,
                                  height: 33,
                                  alignment: Alignment.center,
                                  decoration: BoxDecoration(
                                    borderRadius: BorderRadius.circular(11),
                                    color: mark == st
                                        ? _tone(st)
                                        : Colors.white.withOpacity(0.06),
                                    border: Border.all(
                                        color: mark == st
                                            ? _tone(st)
                                            : Colors.white.withOpacity(0.1)),
                                    boxShadow: mark == st
                                        ? [
                                            BoxShadow(
                                                color: _tone(st)
                                                    .withOpacity(0.5),
                                                blurRadius: 10)
                                          ]
                                        : null,
                                  ),
                                  child: Text(
                                    st.letter,
                                    style: TextStyle(
                                      fontWeight: FontWeight.w800,
                                      fontSize: 12.5,
                                      color: mark == st
                                          ? Colors.white
                                          : Palette.textLo,
                                    ),
                                  ),
                                ),
                              ),
                            ),
                        ],
                      ),
                    );
                  },
                ),
              ),

              // ── Footer ──
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 4, 20, 14),
                child: Row(
                  children: [
                    GlassIconButton(
                      icon: Icons.qr_code_scanner_rounded,
                      color: Palette.cyan,
                      tooltip: 'Scan ID',
                      onTap: _students.isEmpty ? null : _scan,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: GradientButton(
                        label:
                            'Save attendance${absent > 0 ? '  ·  $absent absent' : ''}',
                        icon: Icons.check_rounded,
                        busy: _saving,
                        onPressed: _students.isEmpty ? null : _save,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
