import 'package:dio/dio.dart';

/// A user-presentable error raised by the SUSWallet API layer.
///
/// The backend returns either
/// `{ "error_code": "INSUFFICIENT_FUNDS", "message": "..." }` for domain errors
/// or Laravel's `{ "message": "...", "errors": { "field": ["..."] } }` for 422
/// validation errors.
class ApiException implements Exception {
  const ApiException({
    required this.message,
    this.statusCode,
    this.errorCode,
    this.fieldErrors = const {},
  });

  final String message;
  final int? statusCode;
  final String? errorCode;
  final Map<String, List<String>> fieldErrors;

  bool get isUnauthorized => statusCode == 401;
  bool get isNetworkError => statusCode == null;

  String? fieldError(String field) {
    final errors = fieldErrors[field];
    return (errors == null || errors.isEmpty) ? null : errors.first;
  }

  factory ApiException.fromDio(DioException error) {
    final response = error.response;

    if (response == null) {
      final isTimeout = error.type == DioExceptionType.connectionTimeout ||
          error.type == DioExceptionType.receiveTimeout ||
          error.type == DioExceptionType.sendTimeout;

      return ApiException(
        message: isTimeout
            ? 'The SUSWallet server took too long to respond. Please try again.'
            : 'Can\'t reach the SUSWallet server. Check your connection and try again.',
      );
    }

    final body = response.data;
    String? message;
    String? errorCode;
    final fieldErrors = <String, List<String>>{};

    if (body is Map) {
      message = body['message']?.toString();
      errorCode = body['error_code']?.toString();
      final errors = body['errors'];
      if (errors is Map) {
        errors.forEach((key, value) {
          if (value is List) {
            fieldErrors[key.toString()] =
                value.map((item) => item.toString()).toList();
          } else if (value != null) {
            fieldErrors[key.toString()] = [value.toString()];
          }
        });
      }
    }

    return ApiException(
      message: (message == null || message.isEmpty)
          ? _fallbackMessage(response.statusCode)
          : message,
      statusCode: response.statusCode,
      errorCode: errorCode,
      fieldErrors: fieldErrors,
    );
  }

  static String _fallbackMessage(int? statusCode) {
    return switch (statusCode) {
      401 => 'Your session has expired. Please log in again.',
      403 => 'You are not allowed to do that.',
      404 => 'The requested resource was not found.',
      429 => 'Too many attempts. Please try again later.',
      final code? when code >= 500 =>
        'The SUSWallet server ran into a problem. Please try again.',
      _ => 'Something went wrong. Please try again.',
    };
  }

  @override
  String toString() => message;
}

/// Converts any thrown object into a message suitable for a SnackBar.
String describeError(Object error) {
  if (error is ApiException) return error.message;
  if (error is DioException) return ApiException.fromDio(error).message;
  return 'Something went wrong. Please try again.';
}
