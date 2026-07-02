import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../services/sync_engine.dart';
import 'glass.dart';

/// Tiny frosted status pill — just enough to be noticed.
///   syncing → spinning arc · "Syncing"
///   idle    → green dot   · "14:32"
///   offline → amber dot   · "N queued" (or "Offline")
/// Tapping it forces a sync, but nobody has to: the engine is automatic.
class SyncBadge extends StatelessWidget {
  const SyncBadge({super.key});

  @override
  Widget build(BuildContext context) {
    final engine = context.watch<SyncEngine>();

    late final Color dot;
    late final String label;
    Widget? leading;

    switch (engine.status) {
      case SyncStatus.syncing:
        dot = Palette.cyan;
        label = 'Syncing';
        leading = const SizedBox(
          width: 10,
          height: 10,
          child: CircularProgressIndicator(strokeWidth: 1.6, color: Palette.cyan),
        );
      case SyncStatus.offline:
        dot = Palette.amber;
        label = engine.pending > 0 ? '${engine.pending} queued' : 'Offline';
      case SyncStatus.idle:
        dot = Palette.emerald;
        label = engine.lastSync != null
            ? DateFormat('HH:mm').format(engine.lastSync!)
            : '—';
    }

    return GestureDetector(
      onTap: () => engine.sync(),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: Colors.white.withOpacity(0.07),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: Colors.white.withOpacity(0.12)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            leading ??
                Container(
                  width: 7,
                  height: 7,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: dot,
                    boxShadow: [
                      BoxShadow(color: dot.withOpacity(0.7), blurRadius: 6),
                    ],
                  ),
                ),
            const SizedBox(width: 6),
            Text(label,
                style: const TextStyle(
                    fontSize: 11,
                    color: Palette.textMid,
                    fontWeight: FontWeight.w600)),
          ],
        ),
      ),
    );
  }
}
