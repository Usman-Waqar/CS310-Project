import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/service_style.dart';
import '../../core/utils/date_labels.dart';
import '../../models/transaction.dart';

/// Displays a single [LedgerTransaction] inside a Material [Card] with a
/// working remove action (supports both tap-to-remove and swipe-to-dismiss).
class TransactionCard extends StatelessWidget {
  const TransactionCard({
    super.key,
    required this.transaction,
    required this.onRemove,
  });

  final LedgerTransaction transaction;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final tx = transaction;
    final style = ServiceStyle.of(tx.vendorCode);

    final (IconData icon, Color color) = switch (tx.type) {
      LedgerTransaction.typeDeposit => (Icons.add, AppColors.success),
      LedgerTransaction.typeRefund => (Icons.replay, style.color),
      _ => (style.icon, style.color),
    };

    final amountColor = switch (tx.sign) {
      '+' => AppColors.success,
      '-' => AppColors.navy,
      _ => AppColors.primary,
    };

    final detailLine = [
      if (tx.subtitle != null && tx.subtitle!.isNotEmpty) tx.subtitle!,
      DateLabels.time(tx.createdAt),
    ].join(' · ');

    return Dismissible(
      key: ValueKey('tx_card_${tx.id}'),
      direction: DismissDirection.endToStart,
      onDismissed: (_) => onRemove(),
      background: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.symmetric(horizontal: 20),
        alignment: Alignment.centerRight,
        decoration: BoxDecoration(
          color: AppColors.dangerSoft,
          borderRadius: BorderRadius.circular(18),
        ),
        child: const Icon(
          Icons.delete_outline_rounded,
          color: AppColors.danger,
        ),
      ),
      child: Card(
        margin: const EdgeInsets.only(bottom: 10),
        elevation: 0,
        color: AppColors.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(18),
          side: const BorderSide(color: AppColors.divider),
        ),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.14),
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, color: color, size: 22),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      tx.title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: AppColors.textPrimary,
                        fontSize: 15.5,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      detailLine,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: AppColors.textMuted,
                        fontSize: 12.5,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    tx.amountFormatted,
                    style: TextStyle(
                      color: tx.isRefunded ? AppColors.textMuted : amountColor,
                      fontSize: 15.5,
                      fontWeight: FontWeight.w600,
                      decoration:
                      tx.isRefunded ? TextDecoration.lineThrough : null,
                    ),
                  ),
                  if (tx.isRefunded)
                    const Text(
                      'Refunded',
                      style: TextStyle(
                        color: AppColors.textMuted,
                        fontSize: 11,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                ],
              ),
              const SizedBox(width: 4),
              IconButton(
                onPressed: onRemove,
                tooltip: 'Remove transaction',
                visualDensity: VisualDensity.compact,
                icon: const Icon(
                  Icons.close_rounded,
                  size: 18,
                  color: AppColors.textSubtle,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}