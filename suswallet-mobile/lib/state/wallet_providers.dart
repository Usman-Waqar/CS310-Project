import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../data/suswallet_repository.dart';
import '../models/transaction.dart';
import '../models/wallet.dart';
import 'session_controller.dart';
import 'transactions_provider.dart';

/// Main Wallet + service wallets of the signed-in student.
final walletsProvider = AsyncNotifierProvider<WalletsController, WalletBundle>(
  WalletsController.new,
);

class WalletsController extends AsyncNotifier<WalletBundle> {
  @override
  Future<WalletBundle> build() async {
    final userId = ref.watch(sessionUserIdProvider);
    if (userId == null) return const WalletBundle.empty();
    return ref.read(repositoryProvider).wallets();
  }

  /// Applies the fresh balances returned by a ledger mutation.
  void apply(WalletBundle wallets) => state = AsyncData(wallets);
}

/// Latest three transactions for the Home dashboard.
final recentTransactionsProvider =
    FutureProvider<List<LedgerTransaction>>((ref) async {
  final userId = ref.watch(sessionUserIdProvider);
  if (userId == null) return const [];
  return ref.read(repositoryProvider).recentTransactions();
});

final ledgerActionsProvider = Provider<LedgerActions>(LedgerActions.new);

/// Money-moving operations used by the Deposit and Top up pop-ups. Each one
/// applies the balances from the response and refreshes the recent list.
class LedgerActions {
  LedgerActions(this._ref);

  final Ref _ref;
  static const _uuid = Uuid();

  static String newIdempotencyKey() => _uuid.v4();

  SusWalletRepository get _repository => _ref.read(repositoryProvider);

  Future<LedgerResult> deposit(int amount, {required String idempotencyKey}) =>
      _run(() => _repository.deposit(
            amount: amount,
            idempotencyKey: idempotencyKey,
          ));

  Future<LedgerResult> allocate(
    String vendorCode,
    int amount, {
    required String idempotencyKey,
  }) =>
      _run(() => _repository.allocate(
            vendorCode: vendorCode,
            amount: amount,
            idempotencyKey: idempotencyKey,
          ));

  Future<LedgerResult> _run(Future<LedgerResult> Function() action) async {
    final result = await action();
    _ref.read(walletsProvider.notifier).apply(result.wallets);
    _ref.invalidate(recentTransactionsProvider);
    _ref.read(walletsProvider.notifier).apply(result.wallets);
    _ref.invalidate(recentTransactionsProvider);
    _ref.invalidate(transactionsListProvider);
    return result;
  }

  /// Pull-to-refresh for Home and Services.
  Future<void> refreshAll() async {
    _ref.invalidate(walletsProvider);
    _ref.invalidate(recentTransactionsProvider);
    await Future.wait<Object>([
      _ref.read(walletsProvider.future),
      _ref.read(recentTransactionsProvider.future),
    ]).catchError((_) => const <Object>[]);
    _ref.invalidate(walletsProvider);
    _ref.invalidate(recentTransactionsProvider);
    _ref.invalidate(transactionsListProvider);
  }
}
