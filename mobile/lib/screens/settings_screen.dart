import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../services/attendance_service.dart';
import '../services/auth_service.dart';
import '../services/sync_engine.dart';
import '../ui/glass.dart';
import 'pin_screen.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
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

  Future<void> _editServerUrl() async {
    final controller = TextEditingController(text: ApiClient.instance.baseUrl);
    final url = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: Palette.bg2,
        shape:
            RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Server URL',
            style: TextStyle(color: Palette.textHi, fontSize: 17)),
        content: TextField(
          controller: controller,
          keyboardType: TextInputType.url,
          style: const TextStyle(color: Palette.textHi, fontSize: 14),
          decoration: const InputDecoration(
            hintText: 'https://…/api/v1',
            hintStyle: TextStyle(color: Palette.textLo),
          ),
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx),
              child:
                  const Text('Cancel', style: TextStyle(color: Palette.textLo))),
          FilledButton(
              style: FilledButton.styleFrom(backgroundColor: Palette.indigo),
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

  Future<void> _signOut() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: Palette.bg2,
        shape:
            RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Sign out?',
            style: TextStyle(color: Palette.textHi, fontSize: 17)),
        content: Text(
          _pending.isEmpty
              ? 'Cached data on this device will be cleared.'
              : '⚠ ${_pending.length} attendance session(s) have NOT been uploaded yet and will be LOST. Sync first!',
          style: const TextStyle(color: Palette.textMid, fontSize: 13.5),
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child:
                  const Text('Cancel', style: TextStyle(color: Palette.textLo))),
          FilledButton(
              style: FilledButton.styleFrom(backgroundColor: Palette.rose),
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

  Widget _sectionLabel(String text) => Padding(
        padding: const EdgeInsets.only(left: 6, bottom: 8, top: 4),
        child: Text(text.toUpperCase(),
            style: const TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w800,
                letterSpacing: 1.6,
                color: Palette.textLo)),
      );

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
      borderRadius: BorderRadius.circular(14),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Row(
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: tone.withOpacity(0.14),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: tone.withOpacity(0.3)),
              ),
              child: Icon(icon, size: 18, color: tone),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title,
                      style: const TextStyle(
                          fontWeight: FontWeight.w600,
                          fontSize: 14,
                          color: Palette.textHi)),
                  if (subtitle != null)
                    Text(subtitle,
                        style: const TextStyle(
                            fontSize: 11.5, color: Palette.textLo)),
                ],
              ),
            ),
            if (trailing != null)
              trailing
            else if (onTap != null)
              const Icon(Icons.chevron_right_rounded,
                  size: 20, color: Palette.textLo),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();
    final engine = context.watch<SyncEngine>();

    return AppBackground(
      child: Scaffold(
        backgroundColor: Colors.transparent,
        body: SafeArea(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 8),
                child: Row(
                  children: [
                    GlassIconButton(
                        icon: Icons.arrow_back_rounded,
                        onTap: () => Navigator.of(context).pop()),
                    const SizedBox(width: 12),
                    const Text('Settings',
                        style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                            color: Palette.textHi)),
                  ],
                ),
              ),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
                  children: [
                    // ── Account ──
                    GlassCard(
                      padding: const EdgeInsets.all(16),
                      child: Row(
                        children: [
                          Container(
                            width: 52,
                            height: 52,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              gradient: Palette.accentGradient,
                              borderRadius: BorderRadius.circular(17),
                              boxShadow: [
                                BoxShadow(
                                    color: Palette.violet.withOpacity(0.45),
                                    blurRadius: 18),
                              ],
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
                                Text(auth.user?['name'] ?? '',
                                    style: const TextStyle(
                                        fontWeight: FontWeight.w800,
                                        fontSize: 15.5,
                                        color: Palette.textHi)),
                                Text(auth.user?['email'] ?? '',
                                    style: const TextStyle(
                                        fontSize: 12, color: Palette.textLo)),
                                Text(
                                    '${auth.school?['name'] ?? ''} · ${auth.school?['code'] ?? ''}',
                                    style: const TextStyle(
                                        fontSize: 12, color: Palette.textLo)),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),

                    // ── Security ──
                    _sectionLabel('Security'),
                    GlassCard(
                      padding:
                          const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
                      child: _row(
                        icon: Icons.pin_rounded,
                        tone: Palette.violet,
                        title: auth.hasLocalPin
                            ? 'Change quick-unlock PIN'
                            : 'Set a quick-unlock PIN',
                        subtitle: '4 digits · unlocks the app even offline',
                        onTap: () => Navigator.of(context).push(
                            MaterialPageRoute(
                                builder: (_) =>
                                    const PinScreen(mode: PinMode.setup))),
                      ),
                    ),
                    const SizedBox(height: 20),

                    // ── Sync ──
                    _sectionLabel('Sync'),
                    GlassCard(
                      padding:
                          const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
                      child: Column(
                        children: [
                          _row(
                            icon: engine.status == SyncStatus.offline
                                ? Icons.cloud_off_rounded
                                : Icons.cloud_done_rounded,
                            tone: engine.status == SyncStatus.offline
                                ? Palette.amber
                                : Palette.emerald,
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
                                        strokeWidth: 2,
                                        color: Palette.cyan))
                                : GlassIconButton(
                                    icon: Icons.refresh_rounded,
                                    color: Palette.cyan,
                                    tooltip: 'Sync now',
                                    onTap: () =>
                                        SyncEngine.instance.sync(pull: true),
                                  ),
                          ),
                          if (_pending.isNotEmpty) ...[
                            Container(
                                height: 1,
                                color: Colors.white.withOpacity(0.07)),
                            _row(
                              icon: Icons.schedule_rounded,
                              tone: Palette.amber,
                              title: '${_pending.length} pending upload(s)',
                              subtitle:
                                  'Captured offline — sent automatically',
                            ),
                            for (final row in _pending)
                              Padding(
                                padding: const EdgeInsets.only(
                                    left: 50, bottom: 8),
                                child: Align(
                                  alignment: Alignment.centerLeft,
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                          '${row['class_name'] ?? 'Class ${row['class_id']}'} · ${row['session_date']} (${row['session_type']})',
                                          style: const TextStyle(
                                              fontSize: 12,
                                              color: Palette.textMid)),
                                      if (row['sync_error'] != null)
                                        Text(row['sync_error'].toString(),
                                            style: const TextStyle(
                                                fontSize: 11,
                                                color: Palette.rose)),
                                    ],
                                  ),
                                ),
                              ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),

                    // ── Server ──
                    _sectionLabel('Connection'),
                    GlassCard(
                      padding:
                          const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
                      child: _row(
                        icon: Icons.dns_rounded,
                        tone: Palette.cyan,
                        title: 'Server',
                        subtitle: ApiClient.instance.baseUrl,
                        onTap: _editServerUrl,
                      ),
                    ),
                    const SizedBox(height: 24),

                    // ── Sign out ──
                    GlassCard(
                      padding:
                          const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
                      glow: Palette.rose,
                      child: _row(
                        icon: Icons.logout_rounded,
                        tone: Palette.rose,
                        title: 'Sign out',
                        subtitle: _pending.isEmpty
                            ? 'Clears cached data on this device'
                            : '${_pending.length} session(s) not uploaded yet!',
                        onTap: _signOut,
                      ),
                    ),
                    const SizedBox(height: 16),
                    const Center(
                      child: Text('EduID Teacher v1.1.0',
                          style:
                              TextStyle(fontSize: 11, color: Palette.textLo)),
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
