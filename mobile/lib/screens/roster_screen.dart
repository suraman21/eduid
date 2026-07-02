import 'package:flutter/material.dart';

import '../models/models.dart';
import '../services/attendance_service.dart';
import '../services/data_service.dart';
import '../services/sync_engine.dart';
import '../ui/kit.dart';
import 'scanner_screen.dart';

/// Attendance capture: everyone defaults to PRESENT, P/A/L/E per student,
/// live search, bulk actions, continuous QR scanning. Saving queues the
/// session and the SyncEngine pushes it automatically within a second.
class RosterScreen extends StatefulWidget {
  const RosterScreen(
      {super.key, required this.schoolClass, this.autoScan = false});

  final SchoolClass schoolClass;

  /// Open the QR scanner immediately (home "Scan attendance" quick action).
  final bool autoScan;

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
    if (widget.autoScan && students.isNotEmpty) _scan();
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
    final verified = await Navigator.of(context).push<List<Student>>(
      MaterialPageRoute(
          builder: (_) => ScannerScreen(
              classId: widget.schoolClass.id,
              className: widget.schoolClass.name,
              alreadyScanned: _scanned)),
    );
    if (verified == null || verified.isEmpty || !mounted) return;
    setState(() {
      for (final s in verified) {
        _marks[s.id] = AttendanceStatus.present;
        _scanned.add(s.id);
      }
    });
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(
            '${verified.length} student${verified.length == 1 ? '' : 's'} verified present'),
        duration: const Duration(seconds: 2)));
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
        AttendanceStatus.present => AppColors.green,
        AttendanceStatus.absent => AppColors.red,
        AttendanceStatus.late => AppColors.amber,
        AttendanceStatus.excused => AppColors.blue,
      };

  @override
  Widget build(BuildContext context) {
    final present =
        _marks.values.where((s) => s == AttendanceStatus.present).length;
    final absent =
        _marks.values.where((s) => s == AttendanceStatus.absent).length;
    final visible = _visible;

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(widget.schoolClass.name,
                style: const TextStyle(
                    fontSize: 16, fontWeight: FontWeight.w800)),
            Text('$present present · $absent absent',
                style:
                    const TextStyle(fontSize: 12, color: AppColors.inkLo)),
          ],
        ),
        actions: [
          // Session type
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: DropdownButton<String>(
              value: _sessionType,
              underline: const SizedBox.shrink(),
              style: const TextStyle(fontSize: 13, color: AppColors.inkMid),
              items: const [
                DropdownMenuItem(value: 'morning', child: Text('Morning')),
                DropdownMenuItem(
                    value: 'afternoon', child: Text('Afternoon')),
                DropdownMenuItem(value: 'full_day', child: Text('Full day')),
              ],
              onChanged: (v) =>
                  setState(() => _sessionType = v ?? 'morning'),
            ),
          ),
        ],
      ),
      body: SafeArea(
        child: Column(
          children: [
            // ── Search + bulk actions ──
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 4, 20, 10),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _search,
                      style: const TextStyle(
                          color: AppColors.ink, fontSize: 14),
                      decoration: const InputDecoration(
                        hintText: 'Search name or ID…',
                        prefixIcon: Icon(Icons.search_rounded, size: 20),
                        isDense: true,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton(
                    icon: const Icon(Icons.done_all_rounded,
                        color: AppColors.green),
                    tooltip: 'Mark all present',
                    onPressed: () => _markAll(AttendanceStatus.present),
                  ),
                  IconButton(
                    icon: const Icon(Icons.remove_done_rounded,
                        color: AppColors.red),
                    tooltip: 'Mark all absent',
                    onPressed: () => _markAll(AttendanceStatus.absent),
                  ),
                ],
              ),
            ),

            // ── Student list (fixed row height → fastest possible scroll) ──
            Expanded(
              child: ListView.builder(
                padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
                itemCount: visible.length,
                itemExtent: 68,
                itemBuilder: (context, i) {
                  final s = visible[i];
                  final mark = _marks[s.id] ?? AttendanceStatus.present;
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: AppCard(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      radius: 12,
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(children: [
                                  Flexible(
                                    child: Text(s.fullName,
                                        overflow: TextOverflow.ellipsis,
                                        style: const TextStyle(
                                            fontWeight: FontWeight.w600,
                                            color: AppColors.ink,
                                            fontSize: 14)),
                                  ),
                                  if (_scanned.contains(s.id))
                                    const Padding(
                                      padding: EdgeInsets.only(left: 5),
                                      child: Icon(Icons.verified_rounded,
                                          size: 15, color: AppColors.green),
                                    ),
                                ]),
                                Text(s.uid,
                                    style: const TextStyle(
                                        fontSize: 10.5,
                                        color: AppColors.inkLo)),
                              ],
                            ),
                          ),
                          for (final st in AttendanceStatus.values)
                            Padding(
                              padding: const EdgeInsets.only(left: 5),
                              child: Material(
                                color: mark == st ? _tone(st) : AppColors.bg,
                                borderRadius: BorderRadius.circular(10),
                                child: InkWell(
                                  onTap: () =>
                                      setState(() => _marks[s.id] = st),
                                  borderRadius: BorderRadius.circular(10),
                                  child: SizedBox(
                                    width: 34,
                                    height: 34,
                                    child: Center(
                                      child: Text(
                                        st.letter,
                                        style: TextStyle(
                                          fontWeight: FontWeight.w800,
                                          fontSize: 12.5,
                                          color: mark == st
                                              ? Colors.white
                                              : AppColors.inkMid,
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                        ],
                      ),
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
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _students.isEmpty ? null : _scan,
                      icon: const Icon(Icons.qr_code_scanner_rounded,
                          size: 19),
                      label: const Text('Scan'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    flex: 2,
                    child: PrimaryButton(
                      label:
                          'Save${absent > 0 ? '  ·  $absent absent' : ' attendance'}',
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
    );
  }
}
