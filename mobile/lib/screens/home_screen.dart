import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../services/attendance_service.dart';
import '../services/auth_service.dart';
import '../services/data_service.dart';
import '../services/sync_engine.dart';
import '../ui/kit.dart';
import '../ui/sync_badge.dart';
import 'grades_screen.dart';
import 'roster_screen.dart';
import 'schedule_screen.dart';
import 'settings_screen.dart';

/// Home — greeting, stats, quick actions (Scan attendance / Schedule),
/// tappable class cards and recent activity. Everything reads the offline
/// cache; the SyncEngine refreshes it in the background.
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  List<SchoolClass> _classes = [];
  List<Map<String, dynamic>> _recent = [];
  int _periodsToday = 0;
  SyncEngine? _engine;

  @override
  void initState() {
    super.initState();
    _loadCache();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // Reload the cached lists every time a background sync completes.
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
      _periodsToday = today.where((p) => p.className != null).length;
    });
  }

  Future<void> _openRoster(SchoolClass c, {bool scan = false}) async {
    await Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => RosterScreen(schoolClass: c, autoScan: scan)));
    _loadCache();
  }

  /// "Scan attendance" quick action: pick the class, then go straight to the
  /// camera — the fastest path from home to a scanned student.
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

    // Duplicate guard: this class already has attendance today. Re-scanning
    // is allowed (it EDITS the same session — the server merges, never
    // duplicates) but the teacher should do it on purpose, not by accident.
    if (chosen.recordedToday.isNotEmpty) {
      final go = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Attendance already recorded'),
          content: Text(
              '${chosen.name} already has ${chosen.recordedToday.map((t) => t.replaceAll('_', ' ')).join(' and ')} attendance today. '
              'Scanning again updates the existing record — it will not create a duplicate.'),
          actions: [
            TextButton(
                onPressed: () => Navigator.pop(ctx, false),
                child: const Text('Cancel')),
            FilledButton(
                onPressed: () => Navigator.pop(ctx, true),
                child: const Text('Continue')),
          ],
        ),
      );
      if (go != true || !mounted) return;
    }

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

    return Scaffold(
      body: SafeArea(
        child: RefreshIndicator(
          color: AppColors.primary,
          onRefresh: () => engine.sync(),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
            children: [
              // ── Header ──
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _greeting(firstName),
                          style: const TextStyle(
                              fontSize: 21,
                              fontWeight: FontWeight.w800,
                              color: AppColors.ink,
                              letterSpacing: -0.4),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${auth.school?['name'] ?? ''} · ${DateFormat('EEE, MMM d').format(DateTime.now())}',
                          style: const TextStyle(
                              fontSize: 12.5, color: AppColors.inkLo),
                        ),
                      ],
                    ),
                  ),
                  const SyncBadge(),
                  const SizedBox(width: 4),
                  IconButton(
                    icon: const Icon(Icons.settings_outlined,
                        color: AppColors.inkMid),
                    tooltip: 'Settings',
                    onPressed: () => Navigator.of(context).push(
                        MaterialPageRoute(
                            builder: (_) => const SettingsScreen())),
                  ),
                ],
              ),
              const SizedBox(height: 16),

              // ── Quick actions ──
              Row(
                children: [
                  Expanded(
                    child: AppCard(
                      color: AppColors.primary,
                      borderColor: AppColors.primary,
                      padding: const EdgeInsets.symmetric(
                          horizontal: 14, vertical: 16),
                      onTap: _classes.isEmpty ? null : _scanAttendance,
                      child: const Row(
                        children: [
                          Icon(Icons.qr_code_scanner_rounded,
                              color: Colors.white, size: 22),
                          SizedBox(width: 10),
                          Expanded(
                            child: Text('Scan attendance',
                                style: TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w700,
                                    fontSize: 14)),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: AppCard(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 14, vertical: 16),
                      onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(
                              builder: (_) => const ScheduleScreen())),
                      child: const Row(
                        children: [
                          Icon(Icons.calendar_month_rounded,
                              color: AppColors.primary, size: 22),
                          SizedBox(width: 10),
                          Expanded(
                            child: Text('My schedule',
                                style: TextStyle(
                                    color: AppColors.ink,
                                    fontWeight: FontWeight.w700,
                                    fontSize: 14)),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              // ── Stats row ──
              Row(
                children: [
                  Expanded(
                    child: StatTile(
                        value: '${_classes.length}',
                        label: 'Classes',
                        icon: Icons.grid_view_rounded,
                        color: AppColors.primary),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: StatTile(
                        value: '$totalStudents',
                        label: 'Students',
                        icon: Icons.people_alt_rounded,
                        color: AppColors.blue),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: StatTile(
                        value: '$_periodsToday',
                        label: "Today's periods",
                        icon: Icons.schedule_rounded,
                        color: AppColors.primary),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: StatTile(
                      value: '${engine.pending}',
                      label: 'Queued',
                      icon: Icons.cloud_upload_rounded,
                      color:
                          engine.pending > 0 ? AppColors.amber : AppColors.green,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 22),

              // ── Classes ──
              const SectionLabel('My classes'),
              if (_classes.isEmpty)
                const AppCard(
                  padding: EdgeInsets.all(28),
                  child: Column(
                    children: [
                      Icon(Icons.hourglass_empty_rounded,
                          color: AppColors.inkLo, size: 34),
                      SizedBox(height: 10),
                      Text(
                        'No classes yet — they appear automatically\nafter the first sync.',
                        textAlign: TextAlign.center,
                        style:
                            TextStyle(color: AppColors.inkMid, fontSize: 13),
                      ),
                    ],
                  ),
                ),
              for (final c in _classes)
                AppCard(
                  margin: const EdgeInsets.only(bottom: 10),
                  padding: const EdgeInsets.all(14),
                  // The WHOLE card opens attendance — one tap to work.
                  onTap: () => _openRoster(c),
                  child: Row(
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          color: AppColors.primarySoft,
                          borderRadius: BorderRadius.circular(13),
                        ),
                        alignment: Alignment.center,
                        child: Text(
                          _initial(c),
                          style: const TextStyle(
                              color: AppColors.primary,
                              fontWeight: FontWeight.w800,
                              fontSize: 17),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(c.name,
                                style: const TextStyle(
                                    color: AppColors.ink,
                                    fontWeight: FontWeight.w700,
                                    fontSize: 15)),
                            const SizedBox(height: 2),
                            Text('${c.studentCount} students · tap for attendance',
                                style: const TextStyle(
                                    color: AppColors.inkLo, fontSize: 12)),
                          ],
                        ),
                      ),
                      Material(
                        color: AppColors.blueSoft,
                        borderRadius: BorderRadius.circular(10),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(10),
                          onTap: () => Navigator.of(context).push(
                              MaterialPageRoute(
                                  builder: (_) =>
                                      GradesScreen(schoolClass: c))),
                          child: const Padding(
                            padding: EdgeInsets.symmetric(
                                horizontal: 10, vertical: 7),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(Icons.insights_rounded,
                                    color: AppColors.blue, size: 16),
                                SizedBox(width: 5),
                                Text('Grades',
                                    style: TextStyle(
                                        color: AppColors.blue,
                                        fontWeight: FontWeight.w700,
                                        fontSize: 12)),
                              ],
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 6),
                      const Icon(Icons.chevron_right_rounded,
                          color: AppColors.inkLo),
                    ],
                  ),
                ),

              // ── Recent activity ──
              if (_recent.isNotEmpty) ...[
                const SizedBox(height: 14),
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

  String _greeting(String name) {
    final h = DateTime.now().hour;
    final word =
        h < 12 ? 'Good morning' : (h < 17 ? 'Good afternoon' : 'Good evening');
    return name.isEmpty ? word : '$word, $name';
  }

  String _initial(SchoolClass c) {
    final source = (c.grade ?? c.name).replaceAll(RegExp(r'[^0-9A-Za-z]'), '');
    return source.isEmpty ? '•' : source.substring(0, 1).toUpperCase();
  }
}
