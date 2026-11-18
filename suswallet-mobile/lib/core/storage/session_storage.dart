import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../models/user_profile.dart';

/// Provided in `main()` via `ProviderScope(overrides: ...)` once
/// SharedPreferences has been loaded.
final sharedPreferencesProvider = Provider<SharedPreferences>(
  (ref) => throw UnimplementedError('sharedPreferencesProvider not overridden'),
);

final sessionStorageProvider = Provider<SessionStorage>(
  (ref) => SessionStorage(ref.watch(sharedPreferencesProvider)),
);

/// Persists the Sanctum bearer token and the last known profile so returning
/// students do not have to log in every time.
class SessionStorage {
  SessionStorage(this._prefs);

  final SharedPreferences _prefs;

  static const _tokenKey = 'suswallet.auth_token';
  static const _userKey = 'suswallet.cached_user';

  String? get token => _prefs.getString(_tokenKey);

  Future<void> saveToken(String token) => _prefs.setString(_tokenKey, token);

  UserProfile? get cachedUser {
    final raw = _prefs.getString(_userKey);
    if (raw == null) return null;
    try {
      return UserProfile.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }

  Future<void> saveUser(UserProfile user) =>
      _prefs.setString(_userKey, jsonEncode(user.toJson()));

  Future<void> clear() async {
    await _prefs.remove(_tokenKey);
    await _prefs.remove(_userKey);
  }
}
