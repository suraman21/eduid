import 'dart:convert';

import 'package:sqflite/sqflite.dart';

import '../core/api_client.dart';
import '../core/db.dart';
import '../models/models.dart';

/// Pull-side sync + cached reads (offline-first: every screen reads the local
/// cache; the network only refreshes it).
class DataService {
  DataService._();
  static final DataService instance = DataService._();

  /// Refresh classes + every roster from the API into sqlite.
  /// Returns the number of classes cached. Throws on network failure —
  /// callers decide whether that matters (manual sync) or not (background).
  Future<int> refreshAll() async {
    final res = await ApiClient.instance.get('/classes');
    final list = (res.data['classes'] as List).cast<Map<String, dynamic>>();
    final classes = list.map(SchoolClass.fromApi).toList();

    final d = await LocalDb.instance.db;
    await d.transaction((txn) async {
      await txn.delete('classes');
      for (final c in classes) {
        await txn.insert('classes', c.toDb(), conflictAlgorithm: ConflictAlgorithm.replace);
      }
    });

    // Rosters (incl. qr_token → offline QR verification works after this).
    for (final c in classes) {
      final r = await ApiClient.instance.get('/classes/${c.id}/students');
      final students = (r.data['students'] as List)
          .cast<Map<String, dynamic>>()
          .map((j) => Student.fromApi(j, c.id))
          .toList();
      await d.transaction((txn) async {
        await txn.delete('students', where: 'class_id = ?', whereArgs: [c.id]);
        for (final s in students) {
          await txn.insert('students', s.toDb(), conflictAlgorithm: ConflictAlgorithm.replace);
        }
      });
    }

    // Schedule (best-effort — the school may not use timetables yet).
    try {
      final today = await ApiClient.instance.get('/schedule/today');
      await LocalDb.instance.putMeta('schedule_today', jsonEncode(today.data));
      final week = await ApiClient.instance.get('/schedule/week');
      await LocalDb.instance.putMeta('schedule_week', jsonEncode(week.data));
    } catch (_) {}

    await LocalDb.instance.putMeta('last_sync', DateTime.now().toIso8601String());
    return classes.length;
  }

  Future<List<SchoolClass>> cachedClasses() async {
    final d = await LocalDb.instance.db;
    final rows = await d.query('classes', orderBy: 'name');
    return rows.map(SchoolClass.fromDb).toList();
  }

  Future<List<Student>> cachedRoster(int classId) async {
    final d = await LocalDb.instance.db;
    final rows = await d.query('students',
        where: 'class_id = ?', whereArgs: [classId], orderBy: 'full_name');
    return rows.map(Student.fromDb).toList();
  }

  Future<DateTime?> lastSync() async {
    final v = await LocalDb.instance.getMeta('last_sync');
    return v == null ? null : DateTime.tryParse(v);
  }

  /// Today's periods from the cache (refreshed by refreshAll).
  Future<List<PeriodSlot>> cachedScheduleToday() async {
    final raw = await LocalDb.instance.getMeta('schedule_today');
    if (raw == null) return [];
    final data = jsonDecode(raw) as Map<String, dynamic>;
    return (data['schedule'] as List)
        .cast<Map<String, dynamic>>()
        .map(PeriodSlot.fromApi)
        .toList();
  }

  /// Weekly schedule: day (1=Mon…7=Sun) → periods.
  Future<Map<int, List<PeriodSlot>>> cachedScheduleWeek() async {
    final raw = await LocalDb.instance.getMeta('schedule_week');
    if (raw == null) return {};
    final data = jsonDecode(raw) as Map<String, dynamic>;
    final out = <int, List<PeriodSlot>>{};
    for (final day in (data['week'] as List).cast<Map<String, dynamic>>()) {
      out[day['day'] as int] = (day['periods'] as List)
          .cast<Map<String, dynamic>>()
          .map(PeriodSlot.fromApi)
          .toList();
    }
    return out;
  }

  /// Last cached grades for one class — instant, no network. The screen shows
  /// this immediately and then refreshes with [grades] in the background.
  Future<Map<String, dynamic>?> gradesCached(int classId) async {
    final cached = await LocalDb.instance.getMeta('grades_$classId');
    return cached == null ? null : jsonDecode(cached) as Map<String, dynamic>;
  }

  /// Grades for one class — online fetch with a per-class cache fallback.
  Future<Map<String, dynamic>?> grades(int classId) async {
    try {
      final res = await ApiClient.instance.get('/classes/$classId/grades');
      final data = res.data as Map<String, dynamic>;
      await LocalDb.instance.putMeta('grades_$classId', jsonEncode(data));
      return data;
    } catch (_) {
      final cached = await LocalDb.instance.getMeta('grades_$classId');
      return cached == null ? null : jsonDecode(cached) as Map<String, dynamic>;
    }
  }
}
