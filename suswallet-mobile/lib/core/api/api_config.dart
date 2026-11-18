import 'package:flutter/foundation.dart';

/// Resolves the SUSWallet Laravel API base URL for the current platform.
///
/// Override at build time with:
/// `flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000/api/v1`
class ApiConfig {
  ApiConfig._();

  static const String _override = String.fromEnvironment('API_BASE_URL');

  static String get baseUrl {
    if (_override.isNotEmpty) {
      return _override.endsWith('/')
          ? _override.substring(0, _override.length - 1)
          : _override;
    }

    // The Android emulator reaches the host machine through 10.0.2.2.
    if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
      return 'http://10.0.2.2:8000/api/v1';
    }

    return 'http://127.0.0.1:8000/api/v1';
  }

  /// Scheme + host + port of the API server (used for `/storage/...` media).
  static String get origin => Uri.parse(baseUrl).origin;

  /// Turns a backend media path such as `/storage/avatars/x.jpg` into an
  /// absolute URL reachable from this device.
  static String? resolveMediaUrl(String? path, {String? fallbackUrl}) {
    if (path == null || path.isEmpty) return fallbackUrl;
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    final normalized = path.startsWith('/') ? path : '/$path';
    return '$origin$normalized';
  }

  static const Duration connectTimeout = Duration(seconds: 10);
  static const Duration receiveTimeout = Duration(seconds: 20);
}
