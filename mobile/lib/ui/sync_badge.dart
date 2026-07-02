import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../services/sync_engine.dart';
import 'kit.dart';

/// Tiny status pill — just enough to be noticed.
///   syncing → spinner · "Syncing"
///   idle    → green check · last sync time
///   offline → amber dot · "N queued" (or "Offline")
/// Tapping it forces a sync, but nobody has to: the engine is automatic.
class SyncBadge extends StatelessWidget {
  const SyncBadge({super.key});

  @override
  Widget build(BuildContext context) {
    final engine = context.watch<SyncEngine>();

    late final Color color;
    late final String label;
    late final Widget leading;

    switch (engine.status) {
      case SyncStatus.syncing:
        color = AppColors.blue;
        label = 'Syncing';
        leading = const SizedBox(
          width: 10,
          height: 10,
          child:
              CircularProgressIndicator(strokeWidth: 1.6, color: AppColors.blue),
        );
      case SyncStatus.offline:
        color = AppColors.amber;
        label = engine.pending > 0 ? '${engine.pending} queued' : 'Offline';
        leading = const Icon(Icons.cloud_off_rounded,
            size: 12, color: AppColors.amber);
      case SyncStatus.idle:
        color = AppColors.green;
        label = engine.lastSync != null
            ? DateFormat('HH:mm').format(engine.lastSync!)
            : '—';
        leading = const Icon(Icons.check_circle_rounded,
            size: 12, color: AppColors.green);
    }

    return Material(
      color: color.withOpacity(0.08),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: () => engine.sync(),
        borderRadius: BorderRadius.circular(999),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              leading,
              const SizedBox(width: 5),
              Text(label,
                  style: TextStyle(
                      fontSize: 11, color: color, fontWeight: FontWeight.w700)),
            ],
          ),
        ),
      ),
    );
  }
}
