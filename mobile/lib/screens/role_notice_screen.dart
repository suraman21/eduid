import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/auth_service.dart';
import '../ui/kit.dart';

/// Shown to signed-in roles whose mobile experience isn't live yet (parents,
/// students, …). One EduID app for everyone — features unlock per role.
class RoleNoticeScreen extends StatelessWidget {
  const RoleNoticeScreen({super.key, required this.role});

  final String role;

  @override
  Widget build(BuildContext context) {
    final label = role.isEmpty ? 'this account' : role.replaceAll('_', ' ');
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(28),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const IconTile(Icons.rocket_launch_rounded,
                    color: AppColors.primary, size: 72),
                const SizedBox(height: 20),
                const Text('Coming soon',
                    style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                        color: AppColors.ink)),
                const SizedBox(height: 8),
                Text(
                  'Mobile features for $label accounts are on the way.\n'
                  'You can keep using the web portal in the meantime.',
                  textAlign: TextAlign.center,
                  style:
                      const TextStyle(color: AppColors.inkMid, fontSize: 13.5),
                ),
                const SizedBox(height: 24),
                OutlinedButton.icon(
                  onPressed: () => context.read<AuthService>().signOut(),
                  icon: const Icon(Icons.logout_rounded, size: 18),
                  label: const Text('Sign out'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
