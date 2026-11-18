import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/service_style.dart';
import '../../core/utils/date_labels.dart';
import '../../models/transaction.dart';

/// One row of the Home "Recent Transactions" card: tinted icon, title,
/// subtitle · relative time, signed amount.
class RecentTransactionTile extends StatelessWidget {
  const RecentTransactionTile({super.key, required this.transaction});

  final LedgerTransaction transaction;

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

    final subtitle = [
      if (tx.subtitle != null && tx.subtitle!.isNotEmpty) tx.subtitle!,
      DateLabels.relative(tx.createdAt),
    ].join(' · ');

    return Semantics(
      label: '${tx.title}, $subtitle, ${tx.amountFormatted}'
          '${tx.isRefunded ? ', refunded' : ''}',
      excludeSemantics: true,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Row(
          children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.14),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, color: color, size: 22),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    tx.title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: AppColors.textMuted,
                      fontSize: 13,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 10),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  tx.amountFormatted,
                  style: TextStyle(
                    color: tx.isRefunded ? AppColors.textMuted : amountColor,
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    decoration:
                        tx.isRefunded ? TextDecoration.lineThrough : null,
                  ),
                ),
                if (tx.isRefunded)
                  const Padding(
                    padding: EdgeInsets.only(top: 2),
                    child: Text(
                      'Refunded',
                      style: TextStyle(
                        color: AppColors.textMuted,
                        fontSize: 11.5,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
