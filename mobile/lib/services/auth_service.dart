import 'dart:convert';

import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';
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

  /// Set when the session ended because the server rejected the token (401):
  /// the login screen shows a "signed out" note so the user understands why
  /// they are suddenly back at login. Cleared on the next successful sign-in.
  bool sessionWasExpired = false;

  bool get signedIn => user != null;
  bool get hasLocalPin => _hasLocalPin;
  bool _hasLocalPin = false;

  /// Server rejected our token (a 401 on any request). End the session
  /// locally — no network logout, the token is already dead — and flag it so
  /// login can explain what happened. Called from the ApiClient 401 hook.
  void handleUnauthorized() {
    if (!signedIn) return;
    sessionWasExpired = true;
    signOut(local: true);
  }

  /// Session types the school actually uses (from the login payload / cadence).
  /// The attendance roster offers exactly these instead of a hardcoded set.
  List<String> get sessionTypes {
    final raw = school?['session_types'];
    if (raw is List && raw.isNotEmpty) {
      return raw.map((e) => e.toString()).toList();
    }
    return const ['morning', 'afternoon'];
  }

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
    sessionWasExpired = false;
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
      // Genuinely offline (no server reached) → verify against the local hash.
      // Detected from the exception TYPE, not a message string, so localized
      // or reworded copy can never disable offline unlock.
      if (_isOffline(e)) {
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
      // The server answered. A 423 means the PIN is locked and the server has
      // already revoked our token — force a full re-login. A 401 here is the
      // normal "wrong PIN" answer (with attempts remaining) and is shown as-is.
      final status = e is DioException ? e.response?.statusCode : null;
      if (status == 423) {
        await signOut(local: true);
        return 'For your security, please sign in again.';
      }
      return ApiClient.messageFrom(e);
    }
  }

  /// True when the request never reached the server (no connectivity / DNS /
  /// timeout) — the signal to fall back to offline PIN verification.
  bool _isOffline(Object e) {
    if (e is! DioException) return false;
    return e.type == DioExceptionType.connectionError ||
        e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.sendTimeout ||
        e.type == DioExceptionType.receiveTimeout ||
        (e.type == DioExceptionType.unknown && e.response == null);
  }

  void lock() {
    if (_hasLocalPin && signedIn) {
      locked = true;
      notifyListeners();
    }
  }

  /// Sign out and wipe the device. [local] skips the network logout call —
  /// used when the token is already known dead (a 401/423), so we don't fire a
  /// doomed request that would just 401 again.
  Future<void> signOut({bool local = false}) async {
    if (!local) {
      try {
        await ApiClient.instance.post('/auth/logout');
      } catch (_) {
        // Offline sign-out is fine — the token dies with local storage.
      }
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
