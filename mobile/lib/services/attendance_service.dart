import 'dart:convert';

import 'package:uuid/uuid.dart';

import '../core/api_client.dart';
import '../core/db.dart';
import '../models/models.dart';

/// Push-side attendance: capture offline, queue, sync when a link appears.
///
/// Every captured session gets ONE stable idempotency key (uuid v4) that is
/// reused on every retry — the server treats replays as duplicates, so a
/// flaky 3G link can never double-record a class (contract: /attendance/sync).
class AttendanceService {
  AttendanceService._();
  static final AttendanceService instance = AttendanceService._();

  static const _uuid = Uuid();

  /// Verify a scanned QR payload ("UID|hmac_token") OFFLINE against the
  /// cached roster of [classId]. Returns the student, or null when invalid.
  Future<Student?> verifyQrOffline(String payload, int classId) async {
    final sep = payload.indexOf('|');
    if (sep <= 0) return null;
    final uid = payload.substring(0, sep);
    final token = payload.substring(sep + 1);
    if (token.isEmpty) return null;

    final d = await LocalDb.instance.db;
    final rows = await d.query(
      'students',
      where: 'class_id = ? AND uid = ? AND qr_token = ?',
      whereArgs: [classId, uid, token],
      limit: 1,
    );
    return rows.isEmpty ? null : Student.fromDb(rows.first);
  }

  /// Queue a completed session locally. [marks] is student_id → status.
  Future<void> queueSession({
    required SchoolClass schoolClass,
    required String sessionType, // morning | afternoon | full_day
    required Map<int, AttendanceStatus> marks,
  }) async {
    final records = marks.entries
        .map((e) => {
              'student_id': e.key,
              'status': e.value.apiValue,
              'scanned_at': DateTime.now().toUtc().toIso8601String(),
              'note': null,
            })
        .toList();

    final d = await LocalDb.instance.db;
    await d.insert('attendance_queue', {
      'idempotency_key': _uuid.v4(),
      'class_id': schoolClass.id,
      'class_name': schoolClass.name,
      'session_date': DateTime.now().toIso8601String().substring(0, 10),
      'session_type': sessionType,
      'records_json': jsonEncode(records),
      'created_at': DateTime.now().toIso8601String(),
      'synced': 0,
    });
  }

  Future<int> pendingCount() async {
    final d = await LocalDb.instance.db;
    final rows = await d.rawQuery(
        'SELECT COUNT(*) AS c FROM attendance_queue WHERE synced = 0');
    return (rows.first['c'] as int?) ?? 0;
  }

  Future<List<Map<String, dynamic>>> pendingSessions() async {
    final d = await LocalDb.instance.db;
    return d.query('attendance_queue',
        where: 'synced = 0', orderBy: 'created_at DESC');
  }

  /// Latest captured sessions (synced or not) for the home activity feed.
  Future<List<Map<String, dynamic>>> recentSessions({int limit = 5}) async {
    final d = await LocalDb.instance.db;
    return d.query('attendance_queue',
        orderBy: 'created_at DESC', limit: limit);
  }

  /// Push every unsynced session. Returns (synced, failed).
  /// 'created' / 'merged' / 'duplicate' all count as success — duplicate means
  /// an earlier retry already landed.
  Future<(int, int)> sync() async {
    final d = await LocalDb.instance.db;
    final pending = await d.query('attendance_queue', where: 'synced = 0');
    if (pending.isEmpty) return (0, 0);

    final sessions = pending
        .map((row) => {
              'class_id': row['class_id'],
              'session_date': row['session_date'],
              'session_type': row['session_type'],
              'device_id': 'eduid-teacher-app',
              'idempotency_key': row['idempotency_key'],
              'records': jsonDecode(row['records_json'] as String),
            })
        .toList();

    final res = await ApiClient.instance
        .post('/attendance/sync', data: {'sessions': sessions});

    var ok = 0, failed = 0;
    for (final result
        in (res.data['results'] as List).cast<Map<String, dynamic>>()) {
      final key = result['idempotency_key'] as String?;
      final status = result['status'] as String?;
      if (key == null) continue;
      if (status == 'created' || status == 'merged' || status == 'duplicate') {
        ok++;
        await d.update('attendance_queue', {'synced': 1, 'sync_error': null},
            where: 'idempotency_key = ?', whereArgs: [key]);
      } else {
        failed++;
        await d.update(
            'attendance_queue',
            {'sync_error': (result['message'] ?? 'Rejected by server').toString()},
            where: 'idempotency_key = ?',
            whereArgs: [key]);
      }
    }
    return (ok, failed);
  }
}
