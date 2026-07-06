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
  const RosterScreen({
    super.key,
    required this.schoolClass,
    this.autoScan = false,
    this.sessionTypes = const ['morning', 'afternoon'],
    this.recordedToday = const [],
  });

  final SchoolClass schoolClass;

  /// Open the QR scanner immediately (home "Scan attendance" quick action).
  final bool autoScan;

  /// Session slots the school actually records (from its attendance cadence).
  /// The picker offers exactly these — never a hardcoded morning/afternoon.
  final List<String> sessionTypes;

  /// Session slots already recorded for this class TODAY — used to default to
  /// the NEXT un-recorded slot (so "take morning then afternoon" just works)
  /// and to flag a slot that saving will update rather than create.
  final List<String> recordedToday;

  @override
  State<RosterScreen> createState() => _RosterScreenState();
}

class _RosterScreenState extends State<RosterScreen> {
  List<Student> _students = [];
  final Map<int, AttendanceStatus> _marks = {};
  final Set<int> _scanned = {};
  final _search = TextEditingController();
  late String _sessionType;
  bool _saving = false;

  /// Human label for a session-type key ('full_day' → 'Full day', etc.).
  static String _sessionLabel(String key) => switch (key) {
        'morning' => 'Morning',
        'afternoon' => 'Afternoon',
        'full_day' => 'Full day',
        'once' => 'Full day',
        _ => key
            .split('_')
            .map((w) => w.isEmpty ? '' : '${w[0].toUpperCase()}${w.substring(1)}')
            .join(' '),
      };

  @override
  void initState() {
    super.initState();
    // Default to the first slot NOT yet recorded today — so a teacher who
    // already did the morning lands on the afternoon automatically instead of
    // silently re-opening the morning (the "already marked" confusion).
    final slots = widget.sessionTypes.isNotEmpty
        ? widget.sessionTypes
        : const ['morning'];
    _sessionType = slots.firstWhere(
      (s) => !widget.recordedToday.contains(s),
      orElse: () => slots.first,
    );
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
    // Reflect this slot immediately so reopening the class defaults to the next
    // one (afternoon after morning) even before the next network pull.
    await DataService.instance
        .markRecordedTodayLocally(widget.schoolClass.id, _sessionType);
    // Automatic: the engine pushes this within a second when online.
    SyncEngine.instance.pushSoon();
    if (!mounted) return;
    setState(() => _saving = false);
    final label = _sessionLabel(_sessionType).toLowerCase();
    Navigator.of(context).pop();
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text('$label attendance saved — syncing automatically.')));
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

  /// One session-slot chip (Morning / Afternoon). Selected = filled green;
  /// an already-recorded slot shows a check so the teacher can see at a glance
  /// what's done.
  Widget _sessionChip(String type) {
    final on = _sessionType == type;
    final done = widget.recordedToday.contains(type);
    return Material(
      color: on ? AppColors.primary : AppColors.surface,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: () => setState(() => _sessionType = type),
        borderRadius: BorderRadius.circular(12),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 10),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
                color: on ? AppColors.primary : AppColors.border),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (done)
                Padding(
                  padding: const EdgeInsets.only(right: 5),
                  child: Icon(Icons.check_circle_rounded,
                      size: 15,
                      color: on ? Colors.white : AppColors.primary),
                ),
              Text(_sessionLabel(type),
                  style: TextStyle(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w700,
                      color: on ? Colors.white : AppColors.inkMid)),
            ],
          ),
        ),
      ),
    );
  }

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
      ),
      body: SafeArea(
        child: Column(
          children: [
            // ── Session-type selector (Morning / Afternoon / …) ──
            // Prominent so the teacher always knows and controls which slot
            // they are recording. Slots already recorded today are flagged.
            if (widget.sessionTypes.length > 1)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
                child: Row(
                  children: [
                    for (final t in widget.sessionTypes) ...[
                      Expanded(child: _sessionChip(t)),
                      if (t != widget.sessionTypes.last)
                        const SizedBox(width: 8),
                    ],
                  ],
                ),
              ),
            if (widget.recordedToday.contains(_sessionType))
              Container(
                width: double.infinity,
                margin: const EdgeInsets.fromLTRB(16, 4, 16, 0),
                padding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                decoration: BoxDecoration(
                  color: AppColors.amberSoft,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.info_outline_rounded,
                        size: 16, color: AppColors.amber),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                          '${_sessionLabel(_sessionType)} attendance is already recorded today — saving will update it.',
                          style: const TextStyle(
                              fontSize: 11.5, color: AppColors.amber)),
                    ),
                  ],
                ),
              ),
            const SizedBox(height: 4),
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
              child: visible.isEmpty
                  ? Center(
                      child: Text(
                          _students.isEmpty
                              ? 'No students cached for this class yet.'
                              : 'No students match "${_search.text.trim()}".',
                          style: const TextStyle(
                              color: AppColors.inkLo, fontSize: 13)),
                    )
                  : ListView.builder(
                      padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                      itemCount: visible.length,
                      itemExtent: 70,
                      itemBuilder: (context, i) {
                        final s = visible[i];
                        final mark = _marks[s.id] ?? AttendanceStatus.present;
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: AppCard(
                            padding:
                                const EdgeInsets.fromLTRB(10, 0, 8, 0),
                            radius: 14,
                            child: Row(
                              children: [
                                _Avatar(s.fullName),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Column(
                                    mainAxisAlignment:
                                        MainAxisAlignment.center,
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
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
                                            padding:
                                                EdgeInsets.only(left: 5),
                                            child: Icon(
                                                Icons.verified_rounded,
                                                size: 15,
                                                color: AppColors.green),
                                          ),
                                      ]),
                                      Text('ID: ${s.uid}',
                                          style: const TextStyle(
                                              fontSize: 10.5,
                                              color: AppColors.inkLo)),
                                    ],
                                  ),
                                ),
                                for (final st in AttendanceStatus.values)
                                  _PaleToggle(
                                    status: st,
                                    selected: mark == st,
                                    tone: _tone(st),
                                    onTap: () => setState(
                                        () => _marks[s.id] = st),
                                  ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
            ),

            // ── P/A/L/E legend (colored circular badges, matches the design) ──
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 2, 16, 2),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  for (final e in const [
                    (AttendanceStatus.present, 'Present'),
                    (AttendanceStatus.absent, 'Absent'),
                    (AttendanceStatus.late, 'Late'),
                    (AttendanceStatus.excused, 'Excused'),
                  ])
                    Row(
                      children: [
                        Container(
                          width: 20,
                          height: 20,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                              color: _tone(e.$1), shape: BoxShape.circle),
                          child: Text(e.$1.letter,
                              style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w800)),
                        ),
                        const SizedBox(width: 5),
                        Text(e.$2,
                            style: const TextStyle(
                                fontSize: 11, color: AppColors.inkMid)),
                      ],
                    ),
                ],
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

/// Circular initials avatar (offline-safe — no network image needed).
class _Avatar extends StatelessWidget {
  const _Avatar(this.name);
  final String name;

  String get _initials {
    final parts = name.trim().split(RegExp(r'\s+'));
    if (parts.isEmpty || parts.first.isEmpty) return '?';
    final first = parts.first[0];
    final last = parts.length > 1 ? parts.last[0] : '';
    return '$first$last'.toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 40,
      height: 40,
      alignment: Alignment.center,
      decoration: const BoxDecoration(
          color: AppColors.primarySoft, shape: BoxShape.circle),
      child: Text(_initials,
          style: const TextStyle(
              color: AppColors.primary,
              fontWeight: FontWeight.w800,
              fontSize: 13)),
    );
  }
}

/// One P/A/L/E toggle: a filled colour tile when selected, a quiet letter
/// otherwise — the exact treatment from the design.
class _PaleToggle extends StatelessWidget {
  const _PaleToggle({
    required this.status,
    required this.selected,
    required this.tone,
    required this.onTap,
  });

  final AttendanceStatus status;
  final bool selected;
  final Color tone;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 4),
      child: Material(
        color: selected ? tone : AppColors.bg,
        borderRadius: BorderRadius.circular(11),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(11),
          child: SizedBox(
            width: 34,
            height: 34,
            child: Center(
              child: Text(
                status.letter,
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 13,
                  color: selected ? Colors.white : AppColors.inkLo,
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
