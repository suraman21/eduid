import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../services/auth_service.dart';
import '../services/data_service.dart';
import '../services/sync_engine.dart';
import '../ui/kit.dart';
import '../ui/skeleton.dart';
import 'grades_screen.dart';
import 'roster_screen.dart';

/// The teacher's classes — the dedicated "Classes" tab. Tapping a card opens
/// attendance; the Grades chip opens that class's gradebook. Reads the offline
/// cache and refreshes when a background sync lands.
class ClassesScreen extends StatefulWidget {
  const ClassesScreen({super.key});

  @override
  State<ClassesScreen> createState() => _ClassesScreenState();
}

class _ClassesScreenState extends State<ClassesScreen> {
  List<SchoolClass> _classes = [];
  bool _loading = true;
  SyncEngine? _engine;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
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
    setState(() {
      _classes = classes;
      _loading = false;
    });
  }

  Future<void> _openRoster(SchoolClass c) async {
    final sessionTypes = context.read<AuthService>().sessionTypes;
    await Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => RosterScreen(
            schoolClass: c,
            sessionTypes: sessionTypes,
            recordedToday: c.recordedToday)));
    _load();
  }

  void _openGrades(SchoolClass c) {
    Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => GradesScreen(schoolClass: c, classes: _classes)));
  }

  String _initial(SchoolClass c) {
    final source = (c.grade ?? c.name).replaceAll(RegExp(r'[^0-9A-Za-z]'), '');
    return source.isEmpty ? '•' : source.substring(0, 1).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final engine = context.watch<SyncEngine>();

    return Scaffold(
      appBar: AppBar(title: const Text('Classes')),
      body: _loading
          ? const SkeletonList()
          : _classes.isEmpty
              ? const StateMessage(
                  icon: Icons.grid_view_rounded,
                  title: 'No classes yet',
                  subtitle:
                      'Your classes appear here automatically after the first sync.',
                )
              : RefreshIndicator(
                  color: AppColors.primary,
                  onRefresh: () => engine.sync(),
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                    children: [
                      for (final c in _classes)
                        AppCard(
                          margin: const EdgeInsets.only(bottom: 10),
                          padding: const EdgeInsets.all(14),
                          onTap: () => _openRoster(c),
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
                                child: Text(_initial(c),
                                    style: const TextStyle(
                                        color: AppColors.primary,
                                        fontWeight: FontWeight.w800,
                                        fontSize: 17)),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment:
                                      CrossAxisAlignment.start,
                                  children: [
                                    Text(c.name,
                                        style: const TextStyle(
                                            color: AppColors.ink,
                                            fontWeight: FontWeight.w700,
                                            fontSize: 15)),
                                    const SizedBox(height: 2),
                                    Text(
                                        '${c.studentCount} students · tap for attendance',
                                        style: const TextStyle(
                                            color: AppColors.inkLo,
                                            fontSize: 12)),
                                  ],
                                ),
                              ),
                              Material(
                                color: AppColors.blueSoft,
                                borderRadius: BorderRadius.circular(10),
                                child: InkWell(
                                  borderRadius: BorderRadius.circular(10),
                                  onTap: () => _openGrades(c),
                                  child: const Padding(
                                    padding: EdgeInsets.symmetric(
                                        horizontal: 10, vertical: 7),
                                    child: Row(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        Icon(Icons.insights_rounded,
                                            color: AppColors.blue, size: 16),
                                        SizedBox(width: 5),
                                        Text('Grades',
                                            style: TextStyle(
                                                color: AppColors.blue,
                                                fontWeight: FontWeight.w700,
                                                fontSize: 12)),
                                      ],
                                    ),
                                  ),
                                ),
                              ),
                              const SizedBox(width: 6),
                              const Icon(Icons.chevron_right_rounded,
                                  color: AppColors.inkLo),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
    );
  }
}
