import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api/api_client.dart';
import '../core/api/api_exception.dart';
import '../core/storage/session_storage.dart';
import '../data/suswallet_repository.dart';
import '../models/user_profile.dart';

/// The signed-in student, or `null` when logged out.
final sessionProvider =
    AsyncNotifierProvider<SessionController, UserProfile?>(
  SessionController.new,
);

/// Only changes when a different student signs in or out, so data providers
/// can depend on it without refetching on every profile edit.
final sessionUserIdProvider = Provider<String?>(
  (ref) => ref.watch(sessionProvider.select((session) => session.value?.id)),
);

class SessionController extends AsyncNotifier<UserProfile?> {
  SessionStorage get _storage => ref.read(sessionStorageProvider);
  SusWalletRepository get _repository => ref.read(repositoryProvider);

  @override
  Future<UserProfile?> build() async {
    ref.read(apiClientProvider).onUnauthorized = _handleUnauthorized;

    if (_storage.token == null) return null;

    try {
      final user = await _repository.me();
      await _storage.saveUser(user);
      return user;
    } on ApiException catch (error) {
      if (error.isUnauthorized || error.statusCode == 403) {
        await _storage.clear();
        return null;
      }
      // Offline start: keep the student signed in with the cached profile.
      final cached = _storage.cachedUser;
      if (cached != null) return cached;
      rethrow;
    }
  }

  /// Throws [ApiException] on failure; the login screen shows the message.
  Future<void> login({required String email, required String password}) async {
    final result = await _repository.login(
      email: email.trim().toLowerCase(),
      password: password,
    );
    await _storage.saveToken(result.token);
    await _storage.saveUser(result.user);
    state = AsyncData(result.user);
  }

  Future<void> refreshProfile() async {
    final user = await _repository.me();
    updateUser(user);
  }

  void updateUser(UserProfile user) {
    _storage.saveUser(user);
    state = AsyncData(user);
  }

  void _handleUnauthorized() {
    if (state.value == null) return;
    _storage.clear();
    state = const AsyncData(null);
  }
}
