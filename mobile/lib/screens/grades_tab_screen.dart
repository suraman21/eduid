import 'package:flutter/material.dart';

import '../models/models.dart';
import '../services/data_service.dart';
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

  @override
  void initState() {
    super.initState();
    _load();
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
