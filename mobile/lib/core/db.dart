import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

/// Local offline store (PRD: the app must work fully offline once synced).
///
///   classes           — the teacher's class list
///   students          — cached rosters incl. qr_token for OFFLINE QR verify
///   attendance_queue  — captured sessions awaiting sync (idempotency_key per
///                       session makes retries safe; see /attendance/sync)
///   meta              — key/value cache (schedule JSON, last sync time, …)
class LocalDb {
  LocalDb._();
  static final LocalDb instance = LocalDb._();

  Database? _db;

  Future<Database> get db async {
    if (_db != null) return _db!;
    final path = p.join(await getDatabasesPath(), 'eduid_teacher.db');
    _db = await openDatabase(
      path,
      version: 1,
      onCreate: (db, _) async {
        await db.execute('''
          CREATE TABLE classes (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            grade TEXT,
            section TEXT,
            student_count INTEGER NOT NULL DEFAULT 0
          )
        ''');
        await db.execute('''
          CREATE TABLE students (
            id INTEGER PRIMARY KEY,
            class_id INTEGER NOT NULL,
            uid TEXT NOT NULL,
            full_name TEXT NOT NULL,
            gender TEXT,
            qr_token TEXT,
            has_photo INTEGER NOT NULL DEFAULT 0
          )
        ''');
        await db.execute('CREATE INDEX idx_students_class ON students(class_id)');
        await db.execute('''
          CREATE TABLE attendance_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            idempotency_key TEXT NOT NULL UNIQUE,
            class_id INTEGER NOT NULL,
            class_name TEXT,
            session_date TEXT NOT NULL,
            session_type TEXT NOT NULL,
            records_json TEXT NOT NULL,
            created_at TEXT NOT NULL,
            synced INTEGER NOT NULL DEFAULT 0,
            sync_error TEXT
          )
        ''');
        await db.execute('''
          CREATE TABLE meta (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
          )
        ''');
      },
    );
    return _db!;
  }

  Future<void> putMeta(String key, String value) async {
    final d = await db;
    await d.insert('meta', {'key': key, 'value': value},
        conflictAlgorithm: ConflictAlgorithm.replace);
  }

  Future<String?> getMeta(String key) async {
    final d = await db;
    final rows = await d.query('meta', where: 'key = ?', whereArgs: [key], limit: 1);
    return rows.isEmpty ? null : rows.first['value'] as String;
  }

  /// Wipe everything on sign-out (device may be shared between teachers).
  Future<void> wipe() async {
    final d = await db;
    await d.delete('classes');
    await d.delete('students');
    await d.delete('attendance_queue');
    await d.delete('meta');
  }
}
