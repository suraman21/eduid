import 'dart:convert';

import 'package:crypto/crypto.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../core/api_client.dart';
import '../core/db.dart';

/// Auth session + PIN quick-unlock.
///
/// Sign-in: school_code + email + password → Sanctum token (secure storage).
/// PIN: registered with the server (POST /auth/pin/set) AND salted-hashed
/// locally so quick-unlock works OFFLINE. Server verification is used when
/// online (it enforces the 5-attempt lockout + token revocation); the local
/// hash is the offline fallback with its own 5-attempt cap.
class AuthService extends ChangeNotifier {
  static const _storage = FlutterSecureStorage();

  Map<String, dynamic>? user;   // {id, name, email, role}
  Map<String, dynamic>? school; // {id, name, code, academic_year, ...}
  bool locked = false;          // true when a PIN unlock is required
  int _localPinFailures = 0;

  bool get signedIn => user != null;
  bool get hasLocalPin => _hasLocalPin;
  bool _hasLocalPin = false;

  /// Account role from the server: teacher | school_admin | (future: parent…).
  /// The app routes features by this — one EduID app for every user type.
  String get role => (user?['role'] ?? '').toString();

  // ── Auto-lock ──────────────────────────────────────────────
  //
  // Minutes the app may sit in the background before the PIN gate comes
  // back. 0 = immediately, -1 = never. Industry default: 5 minutes.

  static const _lockTimeoutKey = 'lock_timeout_minutes';
  static const lockTimeoutChoices = [0, 1, 5, 15, 30, -1];

  int lockTimeoutMinutes = 5;
  DateTime? _backgroundedAt;

  Future<void> setLockTimeout(int minutes) async {
    lockTimeoutMinutes = minutes;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_lockTimeoutKey, minutes);
    notifyListeners();
  }

  /// App went to the background. Instant mode locks right away so the PIN
  /// gate is already up when the app reappears (nothing sensitive flashes).
  void appPaused() {
    _backgroundedAt = DateTime.now();
    if (lockTimeoutMinutes == 0) lock();
  }

  /// App came back — lock if it was away longer than the chosen timeout.
  void appResumed() {
    final pausedAt = _backgroundedAt;
    _backgroundedAt = null;
    if (pausedAt == null || lockTimeoutMinutes < 0) return;
    if (!signedIn || !_hasLocalPin || locked) return;

    final away = DateTime.now().difference(pausedAt);
    if (away.inSeconds >= lockTimeoutMinutes * 60) lock();
  }

  /// Restore a previous session from storage (app cold start).
  Future<void> restore() async {
    final token = await ApiClient.instance.readToken();
    if (token == null) return;

    final userJson = await _storage.read(key: 'user_json');
    final schoolJson = await _storage.read(key: 'school_json');
    if (userJson != null) user = jsonDecode(userJson) as Map<String, dynamic>;
    if (schoolJson != null) school = jsonDecode(schoolJson) as Map<String, dynamic>;

    _hasLocalPin = (await _storage.read(key: 'pin_hash')) != null;
    // A restored session with a PIN starts LOCKED (quick-unlock gate).
    locked = signedIn && _hasLocalPin;

    final prefs = await SharedPreferences.getInstance();
    lockTimeoutMinutes = prefs.getInt(_lockTimeoutKey) ?? 5;

    notifyListeners();
  }

  Future<void> login({
    required String schoolCode,
    required String email,
    required String password,
  }) async {
    final res = await ApiClient.instance.post('/auth/login', data: {
      'school_code': schoolCode.trim(),
      'email': email.trim(),
      'password': password,
      'device_name': 'eduid-mobile',
    });

    final data = res.data as Map<String, dynamic>;
    await ApiClient.instance.saveToken(data['token'] as String);
    user = data['user'] as Map<String, dynamic>;
    school = data['school'] as Map<String, dynamic>;
    await _storage.write(key: 'user_json', value: jsonEncode(user));
    await _storage.write(key: 'school_json', value: jsonEncode(school));

    ApiClient.instance.sessionExpired = false;
    locked = false;
    notifyListeners();
  }

  // ── PIN quick-unlock ────────────────────────────────────────

  String _hashPin(String pin, String salt) =>
      sha256.convert(utf8.encode('$salt|$pin|eduid')).toString();

  Future<void> setPin(String pin, String currentPassword) async {
    // Server first (it enforces password re-auth + stores the bcrypt hash)…
    await ApiClient.instance.post('/auth/pin/set', data: {
      'pin': pin,
      'password': currentPassword,
    });
    // …then the local offline copy (salted sha256, never the raw PIN).
    final salt = DateTime.now().microsecondsSinceEpoch.toString();
    await _storage.write(key: 'pin_salt', value: salt);
    await _storage.write(key: 'pin_hash', value: _hashPin(pin, salt));
    _hasLocalPin = true;
    _localPinFailures = 0;
    notifyListeners();
  }

  /// Try to unlock with the PIN. Online → server verify (authoritative);
  /// offline → local hash. Returns an error message, or null on success.
  Future<String?> unlock(String pin) async {
    try {
      await ApiClient.instance.post('/auth/pin/verify', data: {'pin': pin});
      locked = false;
      _localPinFailures = 0;
      notifyListeners();
      return null;
    } catch (e) {
      final msg = ApiClient.messageFrom(e);
      // Network unreachable → offline local verification.
      if (msg.startsWith('Cannot reach')) {
        final salt = await _storage.read(key: 'pin_salt');
        final hash = await _storage.read(key: 'pin_hash');
        if (salt != null && hash != null && _hashPin(pin, salt) == hash) {
          locked = false;
          _localPinFailures = 0;
          notifyListeners();
          return null;
        }
        _localPinFailures++;
        if (_localPinFailures >= 5) {
          await signOut(); // offline cap: full re-auth required
          return 'Too many attempts. Please sign in again.';
        }
        return 'Incorrect PIN (offline). ${5 - _localPinFailures} attempts left.';
      }
      // Server said locked (423) → the token was revoked; force re-login.
      if (ApiClient.instance.sessionExpired || msg.contains('locked') || msg.contains('log in')) {
        await signOut();
        return 'PIN locked. Please sign in again.';
      }
      return msg;
    }
  }

  void lock() {
    if (_hasLocalPin && signedIn) {
      locked = true;
      notifyListeners();
    }
  }

  Future<void> signOut() async {
    try {
      await ApiClient.instance.post('/auth/logout');
    } catch (_) {
      // Offline sign-out is fine — the token dies with local storage.
    }
    await ApiClient.instance.clearToken();
    await _storage.delete(key: 'user_json');
    await _storage.delete(key: 'school_json');
    await _storage.delete(key: 'pin_hash');
    await _storage.delete(key: 'pin_salt');
    await LocalDb.instance.wipe();
    user = null;
    school = null;
    locked = false;
    _hasLocalPin = false;
    _localPinFailures = 0;
    notifyListeners();
  }
}
