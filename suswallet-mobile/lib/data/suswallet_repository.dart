import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api/api_client.dart';
import '../core/utils/money.dart';
import '../models/transaction.dart';
import '../models/user_profile.dart';
import '../models/wallet.dart';

final repositoryProvider = Provider<SusWalletRepository>(
  (ref) => SusWalletRepository(ref.watch(apiClientProvider)),
);

class LoginResult {
  const LoginResult({required this.token, required this.user});

  final String token;
  final UserProfile user;
}

/// Typed access to the `/api/v1` endpoints used by Login, Home and Services.
class SusWalletRepository {
  SusWalletRepository(this._api);

  final ApiClient _api;

  // ---------------------------------------------------------------- Auth --

  Future<LoginResult> login({
    required String email,
    required String password,
    String deviceName = 'SUSWallet Mobile',
  }) async {
    final json = await _api.post('/auth/login', data: {
      'email': email,
      'password': password,
      'device_name': deviceName,
    });
    final data = _data(json);
    return LoginResult(
      token: data['token'].toString(),
      user: UserProfile.fromJson(_map(data['user'])),
    );
  }

  Future<UserProfile> me() async =>
      UserProfile.fromJson(_data(await _api.get('/auth/me')));

  Future<String> resetPassword({
    required String email,
    required String studentId,
    required String newPassword,
  }) async {
    final json = await _api.post('/auth/forgot-password', data: {
      'email': email,
      'student_id': studentId,
      'password': newPassword,
      'password_confirmation': newPassword,
    });
    return (json['message'] ?? 'Password has been reset.').toString();
  }

  // ------------------------------------------------------------- Wallets --

  Future<WalletBundle> wallets() async =>
      WalletBundle.fromJson(_data(await _api.get('/wallets')));

  // -------------------------------------------------------- Transactions --

  /// Latest [limit] transactions across all wallets (Home recent list).
  Future<List<LedgerTransaction>> recentTransactions({int limit = 3}) async {
    final data = _data(await _api.get('/transactions', query: {
      'wallet': 'ALL',
      'limit': limit,
    }));
    final items = data['transactions'];
    return items is List
        ? items
            .whereType<Map>()
            .map((item) =>
                LedgerTransaction.fromJson(Map<String, dynamic>.from(item)))
            .toList()
        : const [];
  }

  Future<LedgerResult> deposit({
    required int amount,
    required String idempotencyKey,
  }) async {
    final json = await _api.post('/transactions/deposit', data: {
      'amount': Money.toApi(amount),
      'idempotency_key': idempotencyKey,
    });
    return LedgerResult.fromJson(json);
  }

  Future<LedgerResult> allocate({
    required String vendorCode,
    required int amount,
    required String idempotencyKey,
  }) async {
    final json = await _api.post('/transactions/allocate', data: {
      'vendor_code': vendorCode,
      'amount': Money.toApi(amount),
      'idempotency_key': idempotencyKey,
    });
    return LedgerResult.fromJson(json);
  }

  // ------------------------------------------------------------- Helpers --

  static Map<String, dynamic> _data(Map<String, dynamic> json) =>
      _map(json['data']);

  static Map<String, dynamic> _map(Object? value) =>
      value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
}
