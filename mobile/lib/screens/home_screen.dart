import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../models/models.dart';
import '../services/attendance_service.dart';
import '../services/auth_service.dart';
import '../services/data_service.dart';
import 'grades_screen.dart';
import 'roster_screen.dart';
import 'schedule_screen.dart';
import 'settings_screen.dart';

/// My Classes + sync state — the app's hub.
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  List<SchoolClass> _classes = [];
  int _pending = 0;
  DateTime? _lastSync;
  bool _syncing = false;

  @override
  void initState() {
    super.initState();
    _loadCache();
  }

  Future<void> _loadCache() async {
    final classes = await DataService.instance.cachedClasses();
    final pending = await AttendanceService.instance.pendingCount();
    final last = await DataService.instance.lastSync();
    if (!mounted) return;
    setState(() {
      _classes = classes;
      _pending = pending;
      _lastSync = last;
    });
  }

  Future<void> _syncNow() async {
    setState(() => _syncing = true);
    String message;
    try {
      // Push captured attendance first, then pull fresh rosters.
      final (ok, failed) = await AttendanceService.instance.sync();
      await DataService.instance.refreshAll();
      message = failed > 0
          ? 'Synced $ok session(s); $failed rejected — check Pending.'
          : 'Sync complete${ok > 0 ? ' — $ok session(s) uploaded' : ''}.';
    } catch (e) {
      message = ApiClient.messageFrom(e);
    }
    await _loadCache();
    if (!mounted) return;
    setState(() => _syncing = false);
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('My Classes',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
            Text(auth.school?['name'] ?? '',
                style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Schedule',
            icon: const Icon(Icons.calendar_month_outlined),
            onPressed: () => Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const ScheduleScreen())),
          ),
          IconButton(
            tooltip: 'Settings',
            icon: const Icon(Icons.settings_outlined),
            onPressed: () => Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const SettingsScreen())),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _syncNow,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // Sync banner
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: _pending > 0 ? const Color(0xFFFFFBEB) : Colors.white,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                    color: _pending > 0
                        ? const Color(0xFFFDE68A)
                        : const Color(0xFFE2E8F0)),
              ),
              child: Row(
                children: [
                  Icon(
                    _pending > 0 ? Icons.cloud_upload_outlined : Icons.cloud_done_outlined,
                    color: _pending > 0 ? const Color(0xFFB45309) : const Color(0xFF059669),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      _pending > 0
                          ? '$_pending attendance session(s) waiting to upload'
                          : _lastSync != null
                              ? 'Up to date · synced ${DateFormat('MMM d, HH:mm').format(_lastSync!)}'
                              : 'Not synced yet — pull down or tap Sync.',
                      style: const TextStyle(fontSize: 13),
                    ),
                  ),
                  TextButton(
                    onPressed: _syncing ? null : _syncNow,
                    child: _syncing
                        ? const SizedBox(
                            width: 16, height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2))
                        : const Text('Sync'),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            if (_classes.isEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 60),
                child: Column(
                  children: [
                    Icon(Icons.class_outlined, size: 48, color: Colors.grey.shade400),
                    const SizedBox(height: 10),
                    Text(
                      'No classes cached yet.\nPull down to sync when online.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.grey.shade600),
                    ),
                  ],
                ),
              ),

            for (final c in _classes)
              Card(
                elevation: 0,
                margin: const EdgeInsets.only(bottom: 10),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                  side: const BorderSide(color: Color(0xFFE2E8F0)),
                ),
                color: Colors.white,
                child: ListTile(
                  contentPadding:
                      const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                  leading: CircleAvatar(
                    backgroundColor: const Color(0xFFEEF2FF),
                    child: Text(
                      (c.grade ?? c.name).replaceAll(RegExp(r'[^0-9A-Za-z]'), '')
                          .padRight(1)
                          .substring(0, 1)
                          .toUpperCase(),
                      style: const TextStyle(
                          color: Color(0xFF4F46E5), fontWeight: FontWeight.w700),
                    ),
                  ),
                  title: Text(c.name,
                      style: const TextStyle(fontWeight: FontWeight.w700)),
                  subtitle: Text('${c.studentCount} students'),
                  trailing: Wrap(
                    spacing: 4,
                    children: [
                      IconButton(
                        tooltip: 'Grades',
                        icon: const Icon(Icons.bar_chart_rounded,
                            color: Color(0xFF6366F1)),
                        onPressed: () => Navigator.of(context).push(
                            MaterialPageRoute(
                                builder: (_) => GradesScreen(schoolClass: c))),
                      ),
                      IconButton(
                        tooltip: 'Take attendance',
                        icon: const Icon(Icons.fact_check_outlined,
                            color: Color(0xFF059669)),
                        onPressed: () async {
                          await Navigator.of(context).push(MaterialPageRoute(
                              builder: (_) => RosterScreen(schoolClass: c)));
                          _loadCache();
                        },
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
