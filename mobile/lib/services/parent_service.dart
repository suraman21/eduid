import 'dart:convert';

import '../core/api_client.dart';
import '../core/db.dart';

/// Parent app data: children, per-child attendance and published grades.
///
/// Online-first with a cache fallback: every successful fetch is stored in
/// the local meta table, so the last-seen data still shows on a dead
/// connection (marked stale by the caller if needed).
class ParentService {
  ParentService._();
  static final ParentService instance = ParentService._();

  Future<Map<String, dynamic>> _fetch(String path, String cacheKey) async {
    try {
      final res = await ApiClient.instance.get(path);
      final data = res.data as Map<String, dynamic>;
      await LocalDb.instance.putMeta(cacheKey, jsonEncode(data));
      return data;
    } catch (_) {
      final cached = await LocalDb.instance.getMeta(cacheKey);
      if (cached != null) return jsonDecode(cached) as Map<String, dynamic>;
      rethrow;
    }
  }

  Future<List<Map<String, dynamic>>> children() async {
    final data = await _fetch('/parent/children', 'parent_children');
    return (data['children'] as List).cast<Map<String, dynamic>>();
  }

  Future<Map<String, dynamic>> attendance(int studentId) =>
      _fetch('/parent/children/$studentId/attendance', 'parent_att_$studentId');

  Future<Map<String, dynamic>> grades(int studentId) =>
      _fetch('/parent/children/$studentId/grades', 'parent_grades_$studentId');
}
