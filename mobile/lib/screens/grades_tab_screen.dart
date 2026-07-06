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
    final classes = _classes;
    if (classes == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Grades')),
        body: const SkeletonList(),
      );
    }
    if (classes.isEmpty) {
      return Scaffold(
        appBar: AppBar(title: const Text('Grades')),
        body: const StateMessage(
          icon: Icons.insights_rounded,
          title: 'No classes yet',
          subtitle:
              'Grades appear here once your classes and their assessments are set up.',
        ),
      );
    }
    // GradesScreen owns the header, stat cards, sort and class switcher.
    return GradesScreen(schoolClass: classes.first, classes: classes);
  }
}
