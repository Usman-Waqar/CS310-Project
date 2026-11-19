import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/suswallet_repository.dart';
import '../models/transaction.dart';
import 'session_controller.dart';

/// Filter options matching backend `WalletFilter` enum and Step 2.1 wireframes.
enum TransactionFilterOption {
  all('ALL', 'All'),
  main('MAIN', 'Main'),
  shuttle('SHUTTLE', 'Shuttle'),
  cafeteria('CAFETERIA', 'Cafeteria'),
  printing('PRINTING', 'Printing');

  const TransactionFilterOption(this.code, this.label);

  final String code;
  final String label;
}

final transactionFilterProvider =
NotifierProvider<TransactionFilterController, TransactionFilterOption>(
  TransactionFilterController.new,
);

class TransactionFilterController extends Notifier<TransactionFilterOption> {
  @override
  TransactionFilterOption build() => TransactionFilterOption.all;

  void select(TransactionFilterOption option) => state = option;
}

/// Tracks transaction IDs removed/hidden from the UI list via Card remove action.
final dismissedTransactionsProvider =
NotifierProvider<DismissedTransactionsController, Set<String>>(
  DismissedTransactionsController.new,
);

class DismissedTransactionsController extends Notifier<Set<String>> {
  @override
  Set<String> build() => <String>{};

  void remove(String id) => state = {...state, id};

  void restore(String id) {
    final next = {...state}..remove(id);
    state = next;
  }

  void clear() => state = <String>{};
}

/// Fetches the monthly summary and filtered transactions for the active tab.
final transactionsListProvider =
FutureProvider<TransactionListData>((ref) async {
  final userId = ref.watch(sessionUserIdProvider);
  if (userId == null) return const TransactionListData.empty();
  final filter = ref.watch(transactionFilterProvider);
  return ref.read(repositoryProvider).transactions(wallet: filter.code);
});