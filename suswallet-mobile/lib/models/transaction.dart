import '../core/utils/money.dart';
import 'wallet.dart';

/// Mirrors the backend `TransactionResource`.
class LedgerTransaction {
  const LedgerTransaction({
    required this.id,
    required this.type,
    required this.status,
    required this.amount,
    required this.amountFormatted,
    required this.sign,
    required this.vendorCode,
    required this.title,
    required this.subtitle,
    required this.isRefunded,
    required this.createdAt,
  });

  static const typeDeposit = 'DEPOSIT';
  static const typeAllocation = 'ALLOCATION';
  static const typeCardTap = 'CARD_TAP';
  static const typeRefund = 'REFUND';

  final String id;
  final String type;
  final String status;

  /// Unsigned amount in kuruş.
  final int amount;

  /// Server formatted amount with its context-aware sign (`-₺21,50`).
  final String amountFormatted;

  /// `+`, `-`, or empty (allocations in the "All" view).
  final String sign;
  final String vendorCode;
  final String title;
  final String? subtitle;
  final bool isRefunded;
  final DateTime createdAt;

  bool get isDeposit => type == typeDeposit;
  bool get isAllocation => type == typeAllocation;
  bool get isCardTap => type == typeCardTap;
  bool get isRefund => type == typeRefund;

  factory LedgerTransaction.fromJson(Map<String, dynamic> json) {
    final amount = Money.parseApi(json['amount']);
    final sign = (json['sign'] ?? '').toString();
    final type = (json['type'] ?? '').toString();
    return LedgerTransaction(
      id: (json['id'] ?? '').toString(),
      type: type,
      status: (json['status'] ?? '').toString(),
      amount: amount,
      amountFormatted: (json['amount_formatted'] ?? Money.format(amount, sign: sign))
          .toString(),
      sign: sign,
      vendorCode: (json['vendor_code'] ?? 'MAIN').toString(),
      title: (json['title'] ?? _defaultTitle(type)).toString(),
      subtitle: json['subtitle']?.toString(),
      isRefunded: json['is_refunded'] == true,
      createdAt:
          DateTime.tryParse((json['created_at'] ?? '').toString())?.toLocal() ??
              DateTime.now(),
    );
  }

  static String _defaultTitle(String type) => switch (type) {
        typeDeposit => 'Card deposit',
        typeAllocation => 'Allocation',
        typeCardTap => 'Card tap',
        typeRefund => 'Refund',
        _ => 'Transaction',
      };
}

/// Response of the money-moving endpoints (deposit, allocate).
class LedgerResult {
  const LedgerResult({
    required this.message,
    required this.replayed,
    required this.transaction,
    required this.wallets,
  });

  final String message;
  final bool replayed;
  final LedgerTransaction transaction;
  final WalletBundle wallets;

  factory LedgerResult.fromJson(Map<String, dynamic> json) {
    final data = Map<String, dynamic>.from(json['data'] as Map);
    return LedgerResult(
      message: (json['message'] ?? '').toString(),
      replayed: json['idempotent_replay'] == true,
      transaction: LedgerTransaction.fromJson(
        Map<String, dynamic>.from(data['transaction'] as Map),
      ),
      wallets: WalletBundle.fromJson(
        Map<String, dynamic>.from(data['wallets'] as Map),
      ),
    );
  }
}

/// Monthly deposited & spent totals returned by `GET /api/v1/transactions`.
class MonthlySummary {
  const MonthlySummary({
    required this.depositedThisMonth,
    required this.depositedThisMonthFormatted,
    required this.spentThisMonth,
    required this.spentThisMonthFormatted,
  });

  const MonthlySummary.empty()
      : depositedThisMonth = 0,
        depositedThisMonthFormatted = '+₺0,00',
        spentThisMonth = 0,
        spentThisMonthFormatted = '-₺0,00';

  final int depositedThisMonth;
  final String depositedThisMonthFormatted;
  final int spentThisMonth;
  final String spentThisMonthFormatted;

  factory MonthlySummary.fromJson(Map<String, dynamic> json) {
    final deposited = Money.parseApi(json['deposited_this_month']);
    final spent = Money.parseApi(json['spent_this_month']);
    return MonthlySummary(
      depositedThisMonth: deposited,
      depositedThisMonthFormatted: (json['deposited_this_month_formatted'] ??
          Money.format(deposited, sign: '+'))
          .toString(),
      spentThisMonth: spent,
      spentThisMonthFormatted:
      (json['spent_this_month_formatted'] ?? Money.format(spent, sign: '-'))
          .toString(),
    );
  }
}

/// Full payload of `GET /api/v1/transactions?wallet=...`.
class TransactionListData {
  const TransactionListData({
    required this.filter,
    required this.summary,
    required this.transactions,
  });

  const TransactionListData.empty()
      : filter = 'ALL',
        summary = const MonthlySummary.empty(),
        transactions = const [];

  final String filter;
  final MonthlySummary summary;
  final List<LedgerTransaction> transactions;

  factory TransactionListData.fromJson(Map<String, dynamic> json) {
    final summaryRaw = json['summary'];
    final items = json['transactions'];
    return TransactionListData(
      filter: (json['filter'] ?? 'ALL').toString(),
      summary: summaryRaw is Map
          ? MonthlySummary.fromJson(Map<String, dynamic>.from(summaryRaw))
          : const MonthlySummary.empty(),
      transactions: items is List
          ? items
          .whereType()
          .map((item) =>
          LedgerTransaction.fromJson(Map<String, dynamic>.from(item)))
          .toList()
          : const [],
    );
  }
}
