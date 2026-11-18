import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../storage/session_storage.dart';
import 'api_config.dart';
import 'api_exception.dart';

final apiClientProvider = Provider<ApiClient>((ref) {
  final client = ApiClient(ref.watch(sessionStorageProvider));
  ref.onDispose(client.close);
  return client;
});

/// Thin wrapper around Dio that attaches the Sanctum bearer token, asks for
/// JSON, and converts every failure into an [ApiException].
class ApiClient {
  ApiClient(this._storage, {Dio? dio})
      : _dio = dio ??
            Dio(
              BaseOptions(
                baseUrl: ApiConfig.baseUrl,
                connectTimeout: ApiConfig.connectTimeout,
                receiveTimeout: ApiConfig.receiveTimeout,
                headers: const {'Accept': 'application/json'},
              ),
            ) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          final token = _storage.token;
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
        onError: (error, handler) {
          final isLoginCall = error.requestOptions.path.contains('/auth/login');
          if (error.response?.statusCode == 401 && !isLoginCall) {
            onUnauthorized?.call();
          }
          handler.next(error);
        },
      ),
    );
  }

  final SessionStorage _storage;
  final Dio _dio;

  /// Called when an authenticated request is rejected with 401 (expired or
  /// revoked token). The session controller uses this to return to Login.
  void Function()? onUnauthorized;

  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? query,
  }) =>
      _send(() => _dio.get<dynamic>(path, queryParameters: query));

  Future<Map<String, dynamic>> post(String path, {Object? data}) =>
      _send(() => _dio.post<dynamic>(path, data: data));

  Future<Map<String, dynamic>> put(String path, {Object? data}) =>
      _send(() => _dio.put<dynamic>(path, data: data));

  Future<Map<String, dynamic>> patch(String path, {Object? data}) =>
      _send(() => _dio.patch<dynamic>(path, data: data));

  Future<Map<String, dynamic>> _send(
    Future<Response<dynamic>> Function() request,
  ) async {
    try {
      final response = await request();
      final body = response.data;
      if (body is Map<String, dynamic>) return body;
      if (body is Map) return Map<String, dynamic>.from(body);
      return <String, dynamic>{};
    } on DioException catch (error) {
      throw ApiException.fromDio(error);
    }
  }

  void close() => _dio.close();
}
