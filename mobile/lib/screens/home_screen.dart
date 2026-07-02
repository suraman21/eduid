import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../services/attendance_service.dart';
import '../services/auth_service.dart';
import '../services/data_service.dart';
import '../services/sync_engine.dart';
import '../ui/glass.dart';
import '../ui/sync_badge.dart';
import 'grades_screen.dart';
import 'roster_screen.dart';
import 'schedule_screen.dart';
import 'settings_screen.dart';

/// Home — greeting, live stats, auto-sync badge, tappable class cards and
/// recent attendance history. Everything reads the offline cache; the
/// SyncEngine refreshes it in the background.
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  List<SchoolClass> _classes = [];
  List<Map<String, dynamic>> _recent = [];
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
    if (!mounted) return;
    setState(() {
      _classes = classes;
      _recent = recent;
    });
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();
    final engine = context.watch<SyncEngine>();
    final totalStudents =
        _classes.fold<int>(0, (sum, c) => sum + c.studentCount);
    final firstName =
        (auth.user?['name'] as String? ?? '').split(' ').firstOrNull ?? '';

    return AppBackground(
      child: Scaffold(
        backgroundColor: Colors.transparent,
        body: SafeArea(
          child: RefreshIndicator(
            color: Palette.violet,
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
                                fontSize: 22,
                                fontWeight: FontWeight.w800,
                                color: Palette.textHi,
                                letterSpacing: -0.4),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            '${auth.school?['name'] ?? ''} · ${DateFormat('EEE, MMM d').format(DateTime.now())}',
                            style: const TextStyle(
                                fontSize: 12.5, color: Palette.textLo),
                          ),
                        ],
                      ),
                    ),
                    const SyncBadge(),
                    const SizedBox(width: 8),
                    GlassIconButton(
                      icon: Icons.calendar_month_rounded,
                      tooltip: 'Schedule',
                      onTap: () => Navigator.of(context).push(MaterialPageRoute(
                          builder: (_) => const ScheduleScreen())),
                    ),
                    const SizedBox(width: 8),
                    GlassIconButton(
                      icon: Icons.settings_rounded,
                      tooltip: 'Settings',
                      onTap: () => Navigator.of(context).push(MaterialPageRoute(
                          builder: (_) => const SettingsScreen())),
                    ),
                  ],
                ),
                const SizedBox(height: 20),

                // ── Stats row ──
                Row(
                  children: [
                    _stat('Classes', '${_classes.length}',
                        Icons.grid_view_rounded, Palette.violet),
                    const SizedBox(width: 10),
                    _stat('Students', '$totalStudents',
                        Icons.people_alt_rounded, Palette.cyan),
                    const SizedBox(width: 10),
                    _stat(
                      'Queued',
                      '${engine.pending}',
                      Icons.cloud_upload_rounded,
                      engine.pending > 0 ? Palette.amber : Palette.emerald,
                    ),
                  ],
                ),
                const SizedBox(height: 26),

                // ── Classes ──
                const _SectionTitle('My Classes'),
                const SizedBox(height: 10),
                if (_classes.isEmpty)
                  const GlassCard(
                    padding: EdgeInsets.all(28),
                    child: Column(
                      children: [
                        Icon(Icons.hourglass_empty_rounded,
                            color: Palette.textLo, size: 34),
                        SizedBox(height: 10),
                        Text(
                          'No classes yet — they appear automatically\nafter the first sync.',
                          textAlign: TextAlign.center,
                          style:
                              TextStyle(color: Palette.textLo, fontSize: 13),
                        ),
                      ],
                    ),
                  ),
                for (final c in _classes)
                  GlassCard(
                    margin: const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.all(16),
                    glow: Palette.indigo,
                    // The WHOLE card opens attendance — one tap to work.
                    onTap: () async {
                      await Navigator.of(context).push(MaterialPageRoute(
                          builder: (_) => RosterScreen(schoolClass: c)));
                      _loadCache();
                    },
                    child: Row(
                      children: [
                        Container(
                          width: 46,
                          height: 46,
                          decoration: BoxDecoration(
                            gradient: Palette.accentGradient,
                            borderRadius: BorderRadius.circular(15),
                            boxShadow: [
                              BoxShadow(
                                  color: Palette.violet.withOpacity(0.4),
                                  blurRadius: 14),
                            ],
                          ),
                          alignment: Alignment.center,
                          child: Text(
                            _initial(c),
                            style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w800,
                                fontSize: 17),
                          ),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(c.name,
                                  style: const TextStyle(
                                      color: Palette.textHi,
                                      fontWeight: FontWeight.w700,
                                      fontSize: 15.5)),
                              const SizedBox(height: 2),
                              Text('${c.studentCount} students · tap for attendance',
                                  style: const TextStyle(
                                      color: Palette.textLo, fontSize: 12)),
                            ],
                          ),
                        ),
                        GlassIconButton(
                          icon: Icons.insights_rounded,
                          color: Palette.cyan,
                          tooltip: 'Grades',
                          onTap: () => Navigator.of(context).push(
                              MaterialPageRoute(
                                  builder: (_) =>
                                      GradesScreen(schoolClass: c))),
                        ),
                        const SizedBox(width: 8),
                        const Icon(Icons.chevron_right_rounded,
                            color: Palette.textLo),
                      ],
                    ),
                  ),

                // ── Recent activity ──
                if (_recent.isNotEmpty) ...[
                  const SizedBox(height: 14),
                  const _SectionTitle('Recent Attendance'),
                  const SizedBox(height: 10),
                  for (final row in _recent)
                    GlassCard(
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
                                ? Palette.emerald
                                : Palette.amber,
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              '${row['class_name'] ?? 'Class'} · ${row['session_date']}',
                              style: const TextStyle(
                                  color: Palette.textMid, fontSize: 13),
                            ),
                          ),
                          Text(
                            (row['synced'] ?? 0) == 1 ? 'synced' : 'queued',
                            style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w700,
                                color: (row['synced'] ?? 0) == 1
                                    ? Palette.emerald
                                    : Palette.amber),
                          ),
                        ],
                      ),
                    ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  String _greeting(String name) {
    final h = DateTime.now().hour;
    final word = h < 12 ? 'Good morning' : (h < 17 ? 'Good afternoon' : 'Good evening');
    return name.isEmpty ? word : '$word, $name';
  }

  String _initial(SchoolClass c) {
    final source = (c.grade ?? c.name).replaceAll(RegExp(r'[^0-9A-Za-z]'), '');
    return source.isEmpty ? '•' : source.substring(0, 1).toUpperCase();
  }

  Widget _stat(String label, String value, IconData icon, Color color) {
    return Expanded(
      child: GlassCard(
        padding: const EdgeInsets.symmetric(vertical: 14),
        radius: 18,
        child: Column(
          children: [
            Icon(icon, color: color, size: 20),
            const SizedBox(height: 6),
            Text(value,
                style: const TextStyle(
                    color: Palette.textHi,
                    fontWeight: FontWeight.w800,
                    fontSize: 18)),
            Text(label,
                style: const TextStyle(color: Palette.textLo, fontSize: 11)),
          ],
        ),
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(
      text.toUpperCase(),
      style: const TextStyle(
        color: Palette.textLo,
        fontSize: 11.5,
        fontWeight: FontWeight.w800,
        letterSpacing: 1.6,
      ),
    );
  }
}
