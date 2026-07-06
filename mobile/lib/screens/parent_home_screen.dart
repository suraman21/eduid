import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../services/auth_service.dart';
import '../services/parent_service.dart';
import '../ui/kit.dart';
import 'parent_child_screen.dart';
import 'settings_screen.dart';

/// Parent home — my children, each opening attendance + grades.
class ParentHomeScreen extends StatefulWidget {
  const ParentHomeScreen({super.key});

  @override
  State<ParentHomeScreen> createState() => _ParentHomeScreenState();
}

class _ParentHomeScreenState extends State<ParentHomeScreen> {
  List<Map<String, dynamic>> _children = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final children = await ParentService.instance.children();
      if (!mounted) return;
      setState(() {
        _children = children;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = ApiClient.messageFrom(e);
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();
    final firstName =
        (auth.user?['name'] as String? ?? '').split(' ').firstOrNull ?? '';
    final hour = DateTime.now().hour;
    final greeting = hour < 12
        ? 'Good morning'
        : hour < 17
            ? 'Good afternoon'
            : 'Good evening';

    return Scaffold(
      body: SafeArea(
        child: RefreshIndicator(
          color: AppColors.primary,
          onRefresh: _load,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
            children: [
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('$greeting${firstName.isEmpty ? '' : ', $firstName'}',
                            style: const TextStyle(
                                fontSize: 21,
                                fontWeight: FontWeight.w800,
                                color: AppColors.ink)),
                        const SizedBox(height: 2),
                        Text(auth.school?['name'] ?? '',
                            style: const TextStyle(
                                fontSize: 12.5, color: AppColors.inkLo)),
                      ],
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.settings_outlined,
                        color: AppColors.inkMid),
                    onPressed: () => Navigator.of(context).push(
                        MaterialPageRoute(
                            builder: (_) => const SettingsScreen())),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              const SectionLabel('My children'),

              if (_loading)
                const Padding(
                  padding: EdgeInsets.all(40),
                  child: Center(
                      child: CircularProgressIndicator(
                          color: AppColors.primary, strokeWidth: 2.6)),
                ),

              if (!_loading && _error != null)
                AppCard(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    children: [
                      Text(_error!,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                              color: AppColors.inkMid, fontSize: 13)),
                      const SizedBox(height: 12),
                      OutlinedButton(
                          onPressed: _load, child: const Text('Try again')),
                    ],
                  ),
                ),

              if (!_loading && _error == null && _children.isEmpty)
                const AppCard(
                  padding: EdgeInsets.all(28),
                  child: Text(
                    'No students are linked to your account yet.\nAsk the school office to link your children.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: AppColors.inkMid, fontSize: 13),
                  ),
                ),

              for (final c in _children)
                AppCard(
                  margin: const EdgeInsets.only(bottom: 10),
                  padding: const EdgeInsets.all(14),
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => ParentChildScreen(
                            studentId: c['id'] as int,
                            studentName: c['full_name']?.toString() ?? '',
                            className: c['class_name']?.toString(),
                          ))),
                  child: Row(
                    children: [
                      Container(
                        width: 46,
                        height: 46,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: AppColors.primarySoft,
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: Text(
                          (c['full_name']?.toString() ?? '?')
                              .trim()
                              .split(' ')
                              .map((w) => w.isEmpty ? '' : w[0])
                              .take(2)
                              .join()
                              .toUpperCase(),
                          style: const TextStyle(
                              color: AppColors.primary,
                              fontWeight: FontWeight.w800,
                              fontSize: 15),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(c['full_name']?.toString() ?? '',
                                style: const TextStyle(
                                    color: AppColors.ink,
                                    fontWeight: FontWeight.w700,
                                    fontSize: 15)),
                            const SizedBox(height: 2),
                            Text(
                                [
                                  if (c['class_name'] != null) c['class_name'],
                                  c['uid'],
                                ].join(' · '),
                                style: const TextStyle(
                                    color: AppColors.inkLo, fontSize: 11.5)),
                          ],
                        ),
                      ),
                      if ((c['status']?.toString() ?? 'active') != 'active')
                        StatusPill(c['status'].toString(),
                            color: AppColors.amber),
                      const Icon(Icons.chevron_right_rounded,
                          color: AppColors.inkLo),
                    ],
                  ),
                ),

              if (!_loading && _error == null && _children.isNotEmpty) ...[
                const SizedBox(height: 8),
                AppCard(
                  padding: const EdgeInsets.all(14),
                  color: AppColors.bg,
                  borderColor: Colors.transparent,
                  child: Row(
                    children: [
                      const IconTile(Icons.support_agent_rounded,
                          color: AppColors.primary, size: 36),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Need help?',
                                style: TextStyle(
                                    fontWeight: FontWeight.w700,
                                    fontSize: 13,
                                    color: AppColors.ink)),
                            Text('Contact your school administrator.',
                                style: const TextStyle(
                                    fontSize: 12, color: AppColors.inkLo)),
                          ],
                        ),
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
}
