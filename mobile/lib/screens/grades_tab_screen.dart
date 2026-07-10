import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../services/data_service.dart';
import '../services/sync_engine.dart';
import '../ui/skeleton.dart';
import 'grades_screen.dart';

/// The "Grades" bottom-nav tab: loads the teacher's classes, then shows the
/// gradebook for the first with an in-place class switcher (GradesScreen).
class GradesTabScreen extends StatefulWidget {
  const GradesTabScreen({super.key});

  @override
  State<GradesTabScreen> createState() => _GradesTabScreenState();
}

class _GradesTabScreenState extends State<GradesTabScreen> {
  List<SchoolClass>? _classes;
  SyncEngine? _engine;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // Reload whenever a background sync lands — this tab lives in an
    // IndexedStack (kept alive) and would otherwise stay on its first (often
    // empty, pre-sync) read forever.
    _engine ??= context.read<SyncEngine>()..addListener(_load);
  }

  @override
  void dispose() {
    _engine?.removeListener(_load);
    super.dispose();
  }

  Future<void> _load() async {
    final classes = await DataService.instance.cachedClasses();
    if (!mounted) return;
    setState(() => _classes = classes);
  }

  @override
  Widget build(BuildContext context) {
    final engine = context.watch<SyncEngine>();
    final classes = _classes;

    // Skeleton until the first cache read completes AND, if it came back empty,
    // until the first sync has resolved — so Grades never flashes "no classes"
    // before the sync fills the cache a moment later.
    if (classes == null || (classes.isEmpty && engine.initialSyncPending)) {
      return Scaffold(
        appBar: AppBar(title: const Text('Grades')),
        body: const SkeletonList(),
      );
    }
    if (classes.isEmpty) {
      final offline = engine.status == SyncStatus.offline;
      return Scaffold(
        appBar: AppBar(title: const Text('Grades')),
        body: StateMessage(
          icon: offline ? Icons.cloud_off_rounded : Icons.insights_rounded,
          title: offline ? "Can't load classes" : 'No classes yet',
          subtitle: offline
              ? 'You appear to be offline. Grades will load once you reconnect.'
              : "You aren't assigned to any classes yet. Ask your school admin to assign you.",
        ),
      );
    }
    // GradesScreen owns the header, stat cards, sort and class switcher.
    return GradesScreen(schoolClass: classes.first, classes: classes);
  }
}
