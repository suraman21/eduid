import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../services/attendance_service.dart';
import '../services/auth_service.dart';
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
        title: const Text('Server URL'),
        content: TextField(
          controller: controller,
          keyboardType: TextInputType.url,
          decoration:
              const InputDecoration(hintText: 'https://…/api/v1'),
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
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

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();

    return Scaffold(
      appBar: AppBar(
          title: const Text('Settings',
              style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800))),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Account
          _card([
            ListTile(
              leading: const CircleAvatar(
                  backgroundColor: Color(0xFFEEF2FF),
                  child: Icon(Icons.person, color: Color(0xFF4F46E5))),
              title: Text(auth.user?['name'] ?? '',
                  style: const TextStyle(fontWeight: FontWeight.w700)),
              subtitle: Text(
                  '${auth.user?['email'] ?? ''}\n${auth.school?['name'] ?? ''} (${auth.school?['code'] ?? ''})'),
              isThreeLine: true,
            ),
          ]),
          const SizedBox(height: 12),

          // Security
          _card([
            ListTile(
              leading: const Icon(Icons.pin_outlined),
              title: Text(auth.hasLocalPin
                  ? 'Change quick-unlock PIN'
                  : 'Set a quick-unlock PIN'),
              subtitle:
                  const Text('4 digits · unlocks the app even offline'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.of(context).push(MaterialPageRoute(
                  builder: (_) => const PinScreen(mode: PinMode.setup))),
            ),
          ]),
          const SizedBox(height: 12),

          // Pending uploads
          _card([
            ListTile(
              leading: const Icon(Icons.cloud_upload_outlined),
              title: Text('Pending uploads (${_pending.length})'),
              subtitle: const Text(
                  'Attendance captured offline, waiting for a connection'),
            ),
            for (final row in _pending)
              ListTile(
                dense: true,
                leading: Icon(
                  row['sync_error'] != null
                      ? Icons.error_outline
                      : Icons.schedule,
                  size: 18,
                  color: row['sync_error'] != null
                      ? const Color(0xFFDC2626)
                      : Colors.grey,
                ),
                title: Text(
                    '${row['class_name'] ?? 'Class ${row['class_id']}'} · ${row['session_date']} (${row['session_type']})',
                    style: const TextStyle(fontSize: 13)),
                subtitle: row['sync_error'] != null
                    ? Text(row['sync_error'].toString(),
                        style: const TextStyle(
                            fontSize: 11, color: Color(0xFFDC2626)))
                    : null,
              ),
          ]),
          const SizedBox(height: 12),

          // Server
          _card([
            ListTile(
              leading: const Icon(Icons.dns_outlined),
              title: const Text('Server'),
              subtitle: Text(ApiClient.instance.baseUrl,
                  style: const TextStyle(fontSize: 12)),
              trailing: const Icon(Icons.edit_outlined, size: 18),
              onTap: _editServerUrl,
            ),
          ]),
          const SizedBox(height: 24),

          FilledButton.tonal(
            style: FilledButton.styleFrom(
              backgroundColor: const Color(0xFFFEF2F2),
              foregroundColor: const Color(0xFFB91C1C),
            ),
            onPressed: () async {
              final confirmed = await showDialog<bool>(
                context: context,
                builder: (ctx) => AlertDialog(
                  title: const Text('Sign out?'),
                  content: Text(_pending.isEmpty
                      ? 'Cached data on this device will be cleared.'
                      : '⚠ ${_pending.length} attendance session(s) have NOT been uploaded yet and will be LOST. Sync first!'),
                  actions: [
                    TextButton(
                        onPressed: () => Navigator.pop(ctx, false),
                        child: const Text('Cancel')),
                    FilledButton(
                        onPressed: () => Navigator.pop(ctx, true),
                        child: const Text('Sign out')),
                  ],
                ),
              );
              if (confirmed == true && context.mounted) {
                await context.read<AuthService>().signOut();
                if (context.mounted) {
                  Navigator.of(context).popUntil((r) => r.isFirst);
                }
              }
            },
            child: const Text('Sign out'),
          ),
          const SizedBox(height: 8),
          Center(
            child: Text('EduID Teacher v1.0.0',
                style: TextStyle(fontSize: 11, color: Colors.grey.shade400)),
          ),
        ],
      ),
    );
  }

  Widget _card(List<Widget> children) => Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFE2E8F0)),
        ),
        child: Column(children: children),
      );
}
