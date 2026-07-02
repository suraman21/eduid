import 'package:flutter/material.dart';

import '../models/models.dart';
import '../services/attendance_service.dart';
import '../services/data_service.dart';
import 'scanner_screen.dart';

/// Attendance capture for one class: every student defaults to PRESENT
/// (the common case — teachers mark the exceptions), P/A/L/E toggles per
/// student, QR scan marks a student present, save queues the session for sync.
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
  String _sessionType = 'morning';
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final students = await DataService.instance.cachedRoster(widget.schoolClass.id);
    if (!mounted) return;
    setState(() {
      _students = students;
      for (final s in students) {
        _marks[s.id] = AttendanceStatus.present;
      }
    });
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
    if (!mounted) return;
    setState(() => _saving = false);
    Navigator.of(context).pop();
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('Attendance saved — it will upload on the next sync.')));
  }

  Color _tone(AttendanceStatus s) => switch (s) {
        AttendanceStatus.present => const Color(0xFF059669),
        AttendanceStatus.absent => const Color(0xFFDC2626),
        AttendanceStatus.late => const Color(0xFFB45309),
        AttendanceStatus.excused => const Color(0xFF6366F1),
      };

  @override
  Widget build(BuildContext context) {
    final absent =
        _marks.values.where((s) => s == AttendanceStatus.absent).length;

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.schoolClass.name,
            style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child: DropdownButton<String>(
              value: _sessionType,
              underline: const SizedBox.shrink(),
              items: const [
                DropdownMenuItem(value: 'morning', child: Text('Morning')),
                DropdownMenuItem(value: 'afternoon', child: Text('Afternoon')),
                DropdownMenuItem(value: 'full_day', child: Text('Full day')),
              ],
              onChanged: (v) => setState(() => _sessionType = v ?? 'morning'),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
              itemCount: _students.length,
              itemBuilder: (context, i) {
                final s = _students[i];
                final mark = _marks[s.id] ?? AttendanceStatus.present;
                return Container(
                  margin: const EdgeInsets.only(bottom: 8),
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
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
                                    style: const TextStyle(fontWeight: FontWeight.w600)),
                              ),
                              if (_scanned.contains(s.id))
                                const Padding(
                                  padding: EdgeInsets.only(left: 6),
                                  child: Icon(Icons.verified_rounded,
                                      size: 16, color: Color(0xFF059669)),
                                ),
                            ]),
                            Text(s.uid,
                                style: TextStyle(
                                    fontSize: 11, color: Colors.grey.shade500)),
                          ],
                        ),
                      ),
                      // P / A / L / E toggles
                      for (final st in AttendanceStatus.values)
                        Padding(
                          padding: const EdgeInsets.only(left: 4),
                          child: InkWell(
                            borderRadius: BorderRadius.circular(10),
                            onTap: () => setState(() => _marks[s.id] = st),
                            child: Container(
                              width: 32, height: 32,
                              alignment: Alignment.center,
                              decoration: BoxDecoration(
                                borderRadius: BorderRadius.circular(10),
                                color: mark == st
                                    ? _tone(st)
                                    : const Color(0xFFF1F5F9),
                              ),
                              child: Text(
                                st.letter,
                                style: TextStyle(
                                  fontWeight: FontWeight.w800,
                                  fontSize: 13,
                                  color: mark == st ? Colors.white : Colors.grey.shade600,
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
          // Footer: scan + save
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
              child: Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      style: OutlinedButton.styleFrom(
                        minimumSize: const Size.fromHeight(48),
                        shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14)),
                      ),
                      onPressed: _students.isEmpty ? null : _scan,
                      icon: const Icon(Icons.qr_code_scanner_rounded),
                      label: const Text('Scan ID'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    flex: 2,
                    child: FilledButton(
                      onPressed: _students.isEmpty || _saving ? null : _save,
                      child: Text(_saving
                          ? 'Saving…'
                          : 'Save attendance${absent > 0 ? ' ($absent absent)' : ''}'),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
