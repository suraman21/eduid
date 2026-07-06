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

    // recorded_today lives OUTSIDE sqlite (it is only valid for today):
    // a day-stamped meta blob, merged back in cachedClasses().
    await LocalDb.instance.putMeta(
      'classes_recorded_today',
      jsonEncode({
        'date': DateTime.now().toIso8601String().substring(0, 10),
        'map': {
          for (final c in classes) '${c.id}': c.recordedToday,
        },
      }),
    );

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

  /// Optimistically record that [sessionType] has been captured for [classId]
  /// TODAY, so reopening the class immediately defaults to the next slot (e.g.
  /// afternoon after morning) even before the next network pull refreshes the
  /// authoritative recorded_today. Safe offline.
  Future<void> markRecordedTodayLocally(int classId, String sessionType) async {
    final today = DateTime.now().toIso8601String().substring(0, 10);
    Map<String, dynamic> blob;
    final raw = await LocalDb.instance.getMeta('classes_recorded_today');
    if (raw != null) {
      try {
        final decoded = jsonDecode(raw);
        blob = decoded is Map<String, dynamic> ? decoded : {};
      } catch (_) {
        blob = {};
      }
    } else {
      blob = {};
    }
    // Reset the map if the cached blob is from a previous day.
    if (blob['date'] != today || blob['map'] is! Map) {
      blob = {'date': today, 'map': <String, dynamic>{}};
    }
    final map = (blob['map'] as Map).cast<String, dynamic>();
    final current = ((map['$classId'] ?? const []) as List)
        .map((e) => e.toString())
        .toSet()
      ..add(sessionType);
    map['$classId'] = current.toList();
    blob['map'] = map;
    await LocalDb.instance.putMeta('classes_recorded_today', jsonEncode(blob));
  }

  Future<List<SchoolClass>> cachedClasses() async {
    final d = await LocalDb.instance.db;
    final rows = await d.query('classes', orderBy: 'name');
    final classes = rows.map(SchoolClass.fromDb).toList();

    // Merge today's recorded-session info (dropped if stale — yesterday's
    // sessions must never trigger today's duplicate warning).
    final raw = await LocalDb.instance.getMeta('classes_recorded_today');
    if (raw != null) {
      try {
        final data = jsonDecode(raw) as Map<String, dynamic>;
        final today = DateTime.now().toIso8601String().substring(0, 10);
        if (data['date'] == today) {
          final map = (data['map'] as Map).cast<String, dynamic>();
          return classes
              .map((c) => SchoolClass(
                    id: c.id,
                    name: c.name,
                    grade: c.grade,
                    section: c.section,
                    studentCount: c.studentCount,
                    recordedToday: ((map['${c.id}'] ?? const []) as List)
                        .map((e) => e.toString())
                        .toList(),
                  ))
              .toList();
        }
      } catch (_) {/* stale/corrupt meta — ignore */}
    }
    return classes;
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

  /// Today's periods from the cache (refreshed by refreshAll). Defensive: a
  /// missing/partial/corrupt blob yields an empty list instead of crashing.
  Future<List<PeriodSlot>> cachedScheduleToday() async {
    final raw = await LocalDb.instance.getMeta('schedule_today');
    if (raw == null) return [];
    try {
      final data = jsonDecode(raw);
      final list = (data is Map ? data['schedule'] : null);
      if (list is! List) return [];
      return list
          .whereType<Map>()
          .map((e) => PeriodSlot.fromApi(e.cast<String, dynamic>()))
          .toList();
    } catch (_) {
      return [];
    }
  }

  /// Weekly schedule: day (1=Mon…7=Sun) → periods. Defensive on shape.
  Future<Map<int, List<PeriodSlot>>> cachedScheduleWeek() async {
    final raw = await LocalDb.instance.getMeta('schedule_week');
    if (raw == null) return {};
    try {
      final data = jsonDecode(raw);
      final week = (data is Map ? data['week'] : null);
      if (week is! List) return {};
      final out = <int, List<PeriodSlot>>{};
      for (final day in week.whereType<Map>()) {
        final dayNum = day['day'];
        final periods = day['periods'];
        if (dayNum is! int || periods is! List) continue;
        out[dayNum] = periods
            .whereType<Map>()
            .map((e) => PeriodSlot.fromApi(e.cast<String, dynamic>()))
            .toList();
      }
      return out;
    } catch (_) {
      return {};
    }
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
