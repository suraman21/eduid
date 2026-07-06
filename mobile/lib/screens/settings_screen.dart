import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../services/attendance_service.dart';
import '../services/auth_service.dart';
import '../services/sync_engine.dart';
import '../ui/kit.dart';
import 'pin_screen.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  static const _version = '1.4.0';

  List<Map<String, dynamic>> _pending = [];

  @override
  void initState() {
    super.initState();
    _loadPending();
    SyncEngine.instance.addListener(_loadPending);
  }

  @override
  void dispose() {
    SyncEngine.instance.removeListener(_loadPending);
    super.dispose();
  }

  Future<void> _loadPending() async {
    final rows = await AttendanceService.instance.pendingSessions();
    if (mounted) setState(() => _pending = rows);
  }

  /// Hidden maintenance option (long-press the version number) — normal users
  /// never see or need this; support staff can repoint a device if a school
  /// is ever migrated to another server.
  Future<void> _editServerUrl() async {
    final controller = TextEditingController(text: ApiClient.instance.baseUrl);
    final url = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Server address'),
        content: TextField(
          controller: controller,
          keyboardType: TextInputType.url,
          decoration: const InputDecoration(hintText: 'https://…/api/v1'),
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Cancel')),
          FilledButton(
              onPressed: () => Navigator.pop(ctx, controller.text),
              child: const Text('Save')),
        ],
      ),
    );
    if (url != null && url.trim().isNotEmpty) {
      await ApiClient.instance.setBaseUrl(url);
      if (mounted) setState(() {});
    }
  }

  String _lockTimeoutLabel(int minutes) => switch (minutes) {
        0 => 'Lock immediately when I leave the app',
        -1 => 'Never lock automatically',
        1 => 'Lock after 1 minute in the background',
        _ => 'Lock after $minutes minutes in the background',
      };

  Future<void> _pickLockTimeout(AuthService auth) async {
    final choice = await showDialog<int>(
      context: context,
      builder: (ctx) => SimpleDialog(
        title: const Text('Auto-lock',
            style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
        children: [
          for (final m in AuthService.lockTimeoutChoices)
            RadioListTile<int>(
              value: m,
              groupValue: auth.lockTimeoutMinutes,
              dense: true,
              activeColor: AppColors.primary,
              title: Text(
                switch (m) {
                  0 => 'Immediately',
                  -1 => 'Never',
                  1 => 'After 1 minute',
                  _ => 'After $m minutes',
                },
                style: const TextStyle(fontSize: 14.5),
              ),
              onChanged: (v) => Navigator.pop(ctx, v),
            ),
        ],
      ),
    );
    if (choice != null) await auth.setLockTimeout(choice);
  }

  Future<void> _signOut() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Sign out?'),
        content: Text(
          _pending.isEmpty
              ? 'Cached data on this device will be cleared.'
              : '${_pending.length} attendance session(s) have NOT been uploaded yet and will be LOST. Connect to the internet and let them sync first.',
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel')),
          FilledButton(
              style: FilledButton.styleFrom(backgroundColor: AppColors.red),
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Sign out')),
        ],
      ),
    );
    if (confirmed == true && mounted) {
      await context.read<AuthService>().signOut();
      if (mounted) Navigator.of(context).popUntil((r) => r.isFirst);
    }
  }

  Widget _row({
    required IconData icon,
    required Color tone,
    required String title,
    String? subtitle,
    Widget? trailing,
    VoidCallback? onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Row(
          children: [
            IconTile(icon, color: tone, size: 38),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title,
                      style: const TextStyle(
                          fontWeight: FontWeight.w600,
                          fontSize: 14,
                          color: AppColors.ink)),
                  if (subtitle != null)
                    Text(subtitle,
                        style: const TextStyle(
                            fontSize: 11.5, color: AppColors.inkLo)),
                ],
              ),
            ),
            if (trailing != null)
              trailing
            else if (onTap != null)
              const Icon(Icons.chevron_right_rounded,
                  size: 20, color: AppColors.inkLo),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();
    final engine = context.watch<SyncEngine>();

    return Scaffold(
      appBar: AppBar(
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        title: const Text('Settings',
            style: TextStyle(
                color: Colors.white, fontWeight: FontWeight.w700)),
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        children: [
          // ── Account ──
          AppCard(
            child: Row(
              children: [
                Container(
                  width: 50,
                  height: 50,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: AppColors.primarySoft,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Text(
                    (auth.user?['name'] ?? '?')
                        .toString()
                        .trim()
                        .split(' ')
                        .map((w) => w.isEmpty ? '' : w[0])
                        .take(2)
                        .join()
                        .toUpperCase(),
                    style: const TextStyle(
                        color: AppColors.primary,
                        fontWeight: FontWeight.w800,
                        fontSize: 16),
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(auth.user?['name'] ?? '',
                          style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 15,
                              color: AppColors.ink)),
                      Text(auth.user?['email'] ?? '',
                          style: const TextStyle(
                              fontSize: 12, color: AppColors.inkLo)),
                      Text(
                          '${auth.school?['name'] ?? ''} · ${auth.school?['code'] ?? ''}',
                          style: const TextStyle(
                              fontSize: 12, color: AppColors.inkLo)),
                    ],
                  ),
                ),
                StatusPill(auth.role.replaceAll('_', ' '),
                    color: AppColors.primary),
              ],
            ),
          ),
          const SizedBox(height: 20),

          // ── Security ──
          const SectionLabel('Security'),
          AppCard(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
            child: Column(
              children: [
                _row(
                  icon: Icons.pin_rounded,
                  tone: AppColors.primary,
                  title: auth.hasLocalPin
                      ? 'Change quick-unlock PIN'
                      : 'Set a quick-unlock PIN',
                  subtitle: '4 digits · unlocks the app even offline',
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => const PinScreen(mode: PinMode.setup))),
                ),
                if (auth.hasLocalPin) ...[
                  const Divider(height: 1),
                  _row(
                    icon: Icons.timer_outlined,
                    tone: AppColors.blue,
                    title: 'Auto-lock',
                    subtitle: _lockTimeoutLabel(auth.lockTimeoutMinutes),
                    onTap: () => _pickLockTimeout(auth),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 20),

          // ── Sync (teacher/admin only — parents fetch on demand) ──
          if (auth.role != 'parent') ...[
          const SectionLabel('Sync'),
          AppCard(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
            child: Column(
              children: [
                _row(
                  icon: engine.status == SyncStatus.offline
                      ? Icons.cloud_off_rounded
                      : Icons.cloud_done_rounded,
                  tone: engine.status == SyncStatus.offline
                      ? AppColors.amber
                      : AppColors.green,
                  title: 'Automatic sync',
                  subtitle: engine.status == SyncStatus.syncing
                      ? 'Syncing now…'
                      : engine.status == SyncStatus.offline
                          ? 'Offline — will sync when back online'
                          : engine.lastSync != null
                              ? 'Everything up to date'
                              : 'Runs every few minutes',
                  trailing: engine.status == SyncStatus.syncing
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: AppColors.blue))
                      : IconButton(
                          icon: const Icon(Icons.refresh_rounded,
                              color: AppColors.blue, size: 20),
                          tooltip: 'Sync now',
                          onPressed: () =>
                              SyncEngine.instance.sync(pull: true),
                        ),
                ),
                if (_pending.isNotEmpty) ...[
                  const Divider(height: 1),
                  _row(
                    icon: Icons.schedule_rounded,
                    tone: AppColors.amber,
                    title: '${_pending.length} pending upload(s)',
                    subtitle: 'Captured offline — sent automatically',
                  ),
                  for (final row in _pending)
                    Padding(
                      padding: const EdgeInsets.only(left: 50, bottom: 8),
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                                '${row['class_name'] ?? 'Class ${row['class_id']}'} · ${row['session_date']} (${row['session_type']})',
                                style: const TextStyle(
                                    fontSize: 12, color: AppColors.inkMid)),
                            if (row['sync_error'] != null)
                              Text(row['sync_error'].toString(),
                                  style: const TextStyle(
                                      fontSize: 11, color: AppColors.red)),
                          ],
                        ),
                      ),
                    ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 20),
          ],

          // ── Sign out ──
          AppCard(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
            child: _row(
              icon: Icons.logout_rounded,
              tone: AppColors.red,
              title: 'Sign out',
              subtitle: _pending.isEmpty
                  ? 'Clears cached data on this device'
                  : '${_pending.length} session(s) not uploaded yet!',
              onTap: _signOut,
            ),
          ),
          const SizedBox(height: 20),

          // ── About ──
          const SectionLabel('About'),
          AppCard(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
            // Long-press = hidden maintenance menu (server address).
            child: GestureDetector(
              onLongPress: _editServerUrl,
              child: _row(
                icon: Icons.info_outline_rounded,
                tone: AppColors.inkMid,
                title: 'App version',
                subtitle: 'EduID v$_version',
              ),
            ),
          ),
          const SizedBox(height: 8),
        ],
      ),
    );
  }
}
