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

  Future<void> logout() async {
    await _api.post('/auth/logout');
  }

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
  Future<String> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    final json = await _api.put('/auth/password', data: {
      'current_password': currentPassword,
      'password': newPassword,
      'password_confirmation': newPassword,
    });
    return (json['message'] ?? 'Password changed successfully.').toString();
  }

  // ------------------------------------------------------------- Profile --

  Future<UserProfile> updateProfile({
    required String fullName,
    String? phone,
  }) async {
    final json = await _api.patch('/profile', data: {
      'full_name': fullName.trim(),
      'phone': (phone == null || phone.trim().isEmpty) ? null : phone.trim(),
    });
    return UserProfile.fromJson(_data(json));
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

  /// Monthly summary + filtered transaction history (`ALL`, `MAIN`, `SHUTTLE`,
  /// `CAFETERIA`, `PRINTING`).
  Future<TransactionListData> transactions({String wallet = 'ALL'}) async {
    final data = _data(await _api.get('/transactions', query: {
      'wallet': wallet,
    }));
    return TransactionListData.fromJson(data);
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
