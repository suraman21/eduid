import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_image_compress/flutter_image_compress.dart';
import 'package:path_provider/path_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../core/api_client.dart';

/// Photo Session Mode plumbing: fetches the active session, compresses
/// captures on-device (data + storage friendly for low-end phones), uploads
/// them, and keeps an offline queue so shooting continues without internet —
/// pending photos flush automatically on the next opportunity.
class PhotoService {
  PhotoService._();
  static final PhotoService instance = PhotoService._();

  static const _pendingKey = 'photo_upload_pending';

  /// The school's active photo session, or null.
  Future<Map<String, dynamic>?> activeSession() async {
    final res = await ApiClient.instance.get('/photo-sessions/active');
    return res.data['session'] as Map<String, dynamic>?;
  }

  /// Compress a captured photo to a sane ID-photo size (~portrait 900px,
  /// JPEG q70 — typically 60–150 KB). Returns the compressed file path.
  Future<String> compress(String srcPath) async {
    final dir = await getApplicationDocumentsDirectory();
    final out =
        '${dir.path}/capture_${DateTime.now().millisecondsSinceEpoch}.jpg';

    final result = await FlutterImageCompress.compressAndGetFile(
      srcPath,
      out,
      quality: 70,
      minWidth: 900,
      minHeight: 900,
      keepExif: false,
    );

    return result?.path ?? srcPath;
  }

  /// Upload one photo. On network failure it is queued for later and the
  /// method reports `queued`. Returns the fresh session payload when online.
  Future<({bool uploaded, Map<String, dynamic>? session})> upload({
    required int sessionId,
    required int studentId,
    required String filePath,
  }) async {
    try {
      final form = FormData.fromMap({
        'student_id': studentId,
        'photo': await MultipartFile.fromFile(filePath, filename: 'photo.jpg'),
      });
      final res = await ApiClient.instance
          .post('/photo-sessions/$sessionId/photo', data: form);

      _deleteQuietly(filePath);
      return (
        uploaded: true,
        session: res.data['session'] as Map<String, dynamic>?
      );
    } on DioException {
      await _enqueue(sessionId, studentId, filePath);
      return (uploaded: false, session: null);
    }
  }

  Future<void> skip(int sessionId) async {
    await ApiClient.instance.post('/photo-sessions/$sessionId/skip');
  }

  // ── Offline queue ─────────────────────────────────────────────

  Future<int> pendingCount() async {
    final prefs = await SharedPreferences.getInstance();
    return (prefs.getStringList(_pendingKey) ?? []).length;
  }

  /// Try to push every queued photo. Safe to call often; stops at the first
  /// network failure (the rest stay queued).
  Future<int> flushPending() async {
    final prefs = await SharedPreferences.getInstance();
    final items = prefs.getStringList(_pendingKey) ?? [];
    var sent = 0;

    for (final raw in List<String>.from(items)) {
      final item = jsonDecode(raw) as Map<String, dynamic>;
      final path = item['path'] as String;

      if (!File(path).existsSync()) {
        items.remove(raw); // file vanished — drop the entry
        continue;
      }

      try {
        final form = FormData.fromMap({
          'student_id': item['student_id'],
          'photo': await MultipartFile.fromFile(path, filename: 'photo.jpg'),
        });
        await ApiClient.instance
            .post('/photo-sessions/${item['session_id']}/photo', data: form);
        items.remove(raw);
        _deleteQuietly(path);
        sent++;
      } on DioException {
        break; // still offline — keep the remainder queued
      }
    }

    await prefs.setStringList(_pendingKey, items);
    return sent;
  }

  Future<void> _enqueue(int sessionId, int studentId, String path) async {
    final prefs = await SharedPreferences.getInstance();
    final items = prefs.getStringList(_pendingKey) ?? [];
    items.add(jsonEncode(
        {'session_id': sessionId, 'student_id': studentId, 'path': path}));
    await prefs.setStringList(_pendingKey, items);
  }

  void _deleteQuietly(String path) {
    try {
      final f = File(path);
      if (f.existsSync()) f.deleteSync();
    } catch (_) {/* best effort */}
  }
}
