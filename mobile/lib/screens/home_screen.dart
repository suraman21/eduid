import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../services/attendance_service.dart';
import '../services/auth_service.dart';
import '../services/data_service.dart';
import '../services/sync_engine.dart';
import '../ui/kit.dart';
import '../ui/sync_badge.dart';
import 'roster_screen.dart';

/// Home — greeting, key stats, the primary Scan Attendance action, what's up
/// next today, and recent activity. Everything reads the offline cache; the
/// SyncEngine refreshes it in the background. Class lists and gradebooks live
/// in their own tabs (reached via the stat tiles' "View all" links).
class HomeScreen extends StatefulWidget {
  const HomeScreen({
    super.key,
    this.onOpenClasses,
    this.onOpenSchedule,
  });

  /// Tab-switch callbacks from the shell (null when the tab doesn't exist for
  /// this role — the matching link/stat is then hidden or non-tappable).
  final VoidCallback? onOpenClasses;
  final VoidCallback? onOpenSchedule;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  List<SchoolClass> _classes = [];
  List<Map<String, dynamic>> _recent = [];
  List<PeriodSlot> _todayTeaching = [];
  SyncEngine? _engine;

  @override
  void initState() {
    super.initState();
    _loadCache();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _engine ??= context.read<SyncEngine>()..addListener(_loadCache);
  }

  @override
  void dispose() {
    _engine?.removeListener(_loadCache);
    super.dispose();
  }

  Future<void> _loadCache() async {
    final classes = await DataService.instance.cachedClasses();
    final recent = await AttendanceService.instance.recentSessions(limit: 5);
    final today = await DataService.instance.cachedScheduleToday();
    if (!mounted) return;
    setState(() {
      _classes = classes;
      _recent = recent;
      _todayTeaching = today.where((p) => p.className != null).toList();
    });
  }

  Future<void> _openRoster(SchoolClass c, {bool scan = false}) async {
    final sessionTypes = context.read<AuthService>().sessionTypes;
    await Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => RosterScreen(
            schoolClass: c,
            autoScan: scan,
            sessionTypes: sessionTypes,
            recordedToday: c.recordedToday)));
    _loadCache();
  }

  /// "Scan attendance": pick the class, then go straight to the camera — the
  /// fastest path from home to a scanned student.
  Future<void> _scanAttendance() async {
    if (_classes.isEmpty) return;
    if (_classes.length == 1) {
      _openRoster(_classes.first, scan: true);
      return;
    }
    final chosen = await showModalBottomSheet<SchoolClass>(
      context: context,
      backgroundColor: AppColors.surface,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Padding(
              padding: EdgeInsets.fromLTRB(20, 18, 20, 8),
              child: Text('Scan attendance for…',
                  style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                      color: AppColors.ink)),
            ),
            for (final c in _classes)
              ListTile(
                leading: const IconTile(Icons.qr_code_scanner_rounded,
                    color: AppColors.primary, size: 36),
                title: Text(c.name,
                    style: const TextStyle(
                        fontWeight: FontWeight.w600, color: AppColors.ink)),
                subtitle: Text(
                    c.recordedToday.isEmpty
                        ? '${c.studentCount} students'
                        : '${c.studentCount} students · ✓ ${c.recordedToday.map((t) => t.replaceAll('_', ' ')).join(', ')} recorded',
                    style: TextStyle(
                        fontSize: 12,
                        color: c.recordedToday.isEmpty
                            ? AppColors.inkLo
                            : AppColors.primary)),
                onTap: () => Navigator.pop(ctx, c),
              ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
    if (chosen == null || !mounted) return;

    // No blocking dialog here anymore: the roster opens on the next un-recorded
    // slot (e.g. afternoon once morning is done) and clearly flags a slot that
    // saving would update — so re-recording is always a deliberate choice.
    _openRoster(chosen, scan: true);
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();
    final engine = context.watch<SyncEngine>();
    final totalStudents =
        _classes.fold<int>(0, (sum, c) => sum + c.studentCount);
    final firstName =
        (auth.user?['name'] as String? ?? '').split(' ').firstOrNull ?? '';
    final recordedCount =
        _classes.where((c) => c.recordedToday.isNotEmpty).length;

    return Scaffold(
      body: SafeArea(
        child: RefreshIndicator(
          color: AppColors.primary,
          onRefresh: () => engine.sync(),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
            children: [
              // ── Header: "Good morning," + green name + wave ──
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('${_greeting()},',
                            style: const TextStyle(
                                fontSize: 21,
                                fontWeight: FontWeight.w800,
                                color: AppColors.ink,
                                letterSpacing: -0.4)),
                        Text(
                          firstName.isEmpty ? '👋' : '$firstName 👋',
                          style: const TextStyle(
                              fontSize: 21,
                              fontWeight: FontWeight.w800,
                              color: AppColors.primary,
                              letterSpacing: -0.4),
                        ),
                        const SizedBox(height: 2),
                        const Text("Here's what's happening today.",
                            style: TextStyle(
                                fontSize: 12.5, color: AppColors.inkLo)),
                      ],
                    ),
                  ),
                  const SyncBadge(),
                ],
              ),
              const SizedBox(height: 18),

              // ── 2×2 stat grid ──
              Row(
                children: [
                  Expanded(
                    child: _StatTile(
                      icon: Icons.people_alt_rounded,
                      value: '$totalStudents',
                      label: 'Students',
                      linkText: widget.onOpenClasses != null ? 'View all' : null,
                      onTap: widget.onOpenClasses,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _StatTile(
                      icon: Icons.menu_book_rounded,
                      value: '${_classes.length}',
                      label: 'Classes',
                      linkText: widget.onOpenClasses != null ? 'View all' : null,
                      onTap: widget.onOpenClasses,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: widget.onOpenSchedule != null
                        ? _StatTile(
                            icon: Icons.calendar_month_rounded,
                            value: '${_todayTeaching.length}',
                            label: "Today's periods",
                            linkText: 'Timetable',
                            onTap: widget.onOpenSchedule,
                          )
                        : _StatTile(
                            icon: Icons.cloud_upload_rounded,
                            value: '${engine.pending}',
                            label: 'Queued',
                            valueColor: engine.pending > 0
                                ? AppColors.amber
                                : AppColors.green,
                          ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _StatTile(
                      icon: Icons.fact_check_rounded,
                      value: _classes.isEmpty
                          ? '—'
                          : '$recordedCount/${_classes.length}',
                      label: 'Recorded today',
                      valueColor: AppColors.primary,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),

              // ── Primary action: Scan Attendance ──
              Material(
                color: _classes.isEmpty ? AppColors.inkLo : AppColors.primary,
                borderRadius: BorderRadius.circular(16),
                child: InkWell(
                  borderRadius: BorderRadius.circular(16),
                  onTap: _classes.isEmpty ? null : _scanAttendance,
                  child: Padding(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 18, vertical: 16),
                    child: Row(
                      children: [
                        Container(
                          width: 44,
                          height: 44,
                          decoration: BoxDecoration(
                            color: Colors.white.withOpacity(0.18),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: const Icon(Icons.qr_code_scanner_rounded,
                              color: Colors.white, size: 24),
                        ),
                        const SizedBox(width: 14),
                        const Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('Scan Attendance',
                                  style: TextStyle(
                                      color: Colors.white,
                                      fontWeight: FontWeight.w800,
                                      fontSize: 16)),
                              SizedBox(height: 2),
                              Text('Start scanning QR codes',
                                  style: TextStyle(
                                      color: Colors.white70, fontSize: 12.5)),
                            ],
                          ),
                        ),
                        const Icon(Icons.arrow_forward_rounded,
                            color: Colors.white, size: 20),
                      ],
                    ),
                  ),
                ),
              ),

              // ── Up next (next teaching period today) ──
              if (_nextPeriod() != null) ...[
                const SizedBox(height: 22),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const SectionLabel('Up next'),
                    if (widget.onOpenSchedule != null)
                      GestureDetector(
                        onTap: widget.onOpenSchedule,
                        child: const Padding(
                          padding: EdgeInsets.only(right: 4, bottom: 8),
                          child: Text('View all',
                              style: TextStyle(
                                  color: AppColors.primary,
                                  fontWeight: FontWeight.w700,
                                  fontSize: 12)),
                        ),
                      ),
                  ],
                ),
                _upNextCard(_nextPeriod()!),
              ],

              // ── Recent activity ──
              if (_recent.isNotEmpty) ...[
                const SizedBox(height: 22),
                const SectionLabel('Recent attendance'),
                for (final row in _recent)
                  AppCard(
                    margin: const EdgeInsets.only(bottom: 8),
                    padding: const EdgeInsets.symmetric(
                        horizontal: 14, vertical: 12),
                    child: Row(
                      children: [
                        Icon(
                          (row['synced'] ?? 0) == 1
                              ? Icons.cloud_done_rounded
                              : Icons.schedule_rounded,
                          size: 18,
                          color: (row['synced'] ?? 0) == 1
                              ? AppColors.green
                              : AppColors.amber,
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            '${row['class_name'] ?? 'Class'} · ${row['session_date']}',
                            style: const TextStyle(
                                color: AppColors.inkMid, fontSize: 13),
                          ),
                        ),
                        StatusPill(
                          (row['synced'] ?? 0) == 1 ? 'synced' : 'queued',
                          color: (row['synced'] ?? 0) == 1
                              ? AppColors.green
                              : AppColors.amber,
                        ),
                      ],
                    ),
                  ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  String _greeting() {
    final h = DateTime.now().hour;
    return h < 12 ? 'Good morning' : (h < 17 ? 'Good afternoon' : 'Good evening');
  }

  /// The current or next teaching period today (by start time). Null outside
  /// teaching hours or when there's no timetable.
  PeriodSlot? _nextPeriod() {
    if (_todayTeaching.isEmpty) return null;
    final now = TimeOfDay.now();
    final nowM = now.hour * 60 + now.minute;
    int toM(String t) {
      final p = t.split(':');
      if (p.length < 2) return -1;
      return (int.tryParse(p[0]) ?? 0) * 60 + (int.tryParse(p[1]) ?? 0);
    }

    // Prefer the period happening now, else the next upcoming one.
    for (final p in _todayTeaching) {
      final end = toM(p.endTime);
      if (end >= nowM) return p;
    }
    return null;
  }

  Widget _upNextCard(PeriodSlot p) {
    String hhmm(String t) => t.length >= 5 ? t.substring(0, 5) : t;
    final now = TimeOfDay.now();
    final nowM = now.hour * 60 + now.minute;
    final startParts = p.startTime.split(':');
    final startM = startParts.length >= 2
        ? (int.tryParse(startParts[0]) ?? 0) * 60 +
            (int.tryParse(startParts[1]) ?? 0)
        : 0;
    final live = nowM >= startM;

    return AppCard(
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: AppColors.primarySoft,
              borderRadius: BorderRadius.circular(13),
            ),
            child: const Icon(Icons.menu_book_rounded,
                color: AppColors.primary, size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(p.subjectName ?? p.className ?? p.label,
                    style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 15,
                        color: AppColors.ink)),
                const SizedBox(height: 2),
                Text(
                  [
                    '${hhmm(p.startTime)}–${hhmm(p.endTime)}',
                    if (p.className != null) p.className,
                    if (p.room != null) 'Room ${p.room}',
                  ].whereType<String>().join(' · '),
                  style: const TextStyle(
                      fontSize: 12, color: AppColors.inkLo),
                ),
              ],
            ),
          ),
          if (live) const StatusPill('Now', color: AppColors.primary),
        ],
      ),
    );
  }
}

/// Home stat tile: soft icon chip, big value, label, and an optional green
/// "View all" link — the exact layout from the teacher dashboard design.
class _StatTile extends StatelessWidget {
  const _StatTile({
    required this.icon,
    required this.value,
    required this.label,
    this.linkText,
    this.onTap,
    this.valueColor,
  });

  final IconData icon;
  final String value;
  final String label;
  final String? linkText;
  final VoidCallback? onTap;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    return AppCard(
      onTap: onTap,
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: AppColors.primarySoft,
              borderRadius: BorderRadius.circular(11),
            ),
            child: Icon(icon, color: AppColors.primary, size: 20),
          ),
          const SizedBox(height: 12),
          Text(value,
              style: TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.w800,
                  color: valueColor ?? AppColors.ink,
                  height: 1.0)),
          const SizedBox(height: 2),
          Text(label,
              style: const TextStyle(fontSize: 12, color: AppColors.inkMid)),
          if (linkText != null) ...[
            const SizedBox(height: 8),
            Row(
              children: [
                Text(linkText!,
                    style: const TextStyle(
                        color: AppColors.primary,
                        fontWeight: FontWeight.w700,
                        fontSize: 11.5)),
                const Icon(Icons.chevron_right_rounded,
                    color: AppColors.primary, size: 15),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
