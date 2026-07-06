import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Thin Dio wrapper for the EduID API (contract: docs/MOBILE_API.md).
///
/// - Base URL is configurable in Settings (multi-server / staging support).
/// - The Sanctum bearer token is injected from secure storage on every call.
/// - A 401 marks the session dead so the UI can route back to login.
class ApiClient {
  ApiClient._();
  static final ApiClient instance = ApiClient._();

  static const _defaultBaseUrl = 'https://arkeoid.arkeonethiopia.com/api/v1';
  static const _storage = FlutterSecureStorage();

  final Dio _dio = Dio(BaseOptions(
    connectTimeout: const Duration(seconds: 15),
    receiveTimeout: const Duration(seconds: 30),
    headers: {'Accept': 'application/json'},
  ));

  /// Set when any request gets a 401 — AuthService watches this.
  bool sessionExpired = false;

  /// Invoked once when a request comes back 401 (token expired or revoked
  /// server-side). Wired in main() to end the session and route to login so
  /// the user is never stranded on a dead token with silently failing syncs.
  void Function()? onUnauthorized;

  Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    _dio.options.baseUrl = prefs.getString('server_url') ?? _defaultBaseUrl;

    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await _storage.read(key: 'api_token');
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
      onError: (e, handler) {
        // A 401 on a normal (data) endpoint means our token is dead → end the
        // session. But the auth endpoints legitimately answer 401 as part of
        // their own flow (login = bad credentials, pin/verify = wrong PIN) and
        // must NOT trigger a forced sign-out — otherwise one wrong PIN would
        // wipe the whole session.
        final path = e.requestOptions.path;
        final isAuthEndpoint = path.contains('/auth/login') ||
            path.contains('/auth/pin/');
        if (e.response?.statusCode == 401 && !isAuthEndpoint && !sessionExpired) {
          sessionExpired = true;
          onUnauthorized?.call();
        }
        handler.next(e);
      },
    ));
  }

  Future<void> setBaseUrl(String url) async {
    final cleaned = url.trim().replaceAll(RegExp(r'/+$'), '');
    _dio.options.baseUrl = cleaned;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('server_url', cleaned);
  }

  String get baseUrl => _dio.options.baseUrl;

  Future<void> saveToken(String token) => _storage.write(key: 'api_token', value: token);
  Future<String?> readToken() => _storage.read(key: 'api_token');
  Future<void> clearToken() => _storage.delete(key: 'api_token');

  Future<Response<dynamic>> get(String path, {Map<String, dynamic>? query}) =>
      _dio.get(path, queryParameters: query);

  Future<Response<dynamic>> post(String path, {Object? data}) =>
      _dio.post(path, data: data);

  /// Human-readable message from a DioException (Laravel error envelope).
  static String messageFrom(Object error) {
    if (error is DioException) {
      final data = error.response?.data;
      if (data is Map && data['message'] is String && (data['message'] as String).isNotEmpty) {
        return data['message'] as String;
      }
      if (error.type == DioExceptionType.connectionTimeout ||
          error.type == DioExceptionType.connectionError) {
        return 'Cannot reach the server. Check your connection.';
      }
    }
    return 'Something went wrong. Please try again.';
  }
}
