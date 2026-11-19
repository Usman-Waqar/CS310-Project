import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/date_labels.dart';
import '../../models/transaction.dart';
import '../../state/transactions_provider.dart';
import '../../widgets/common.dart';
import 'transaction_card.dart';

class TransactionsScreen extends ConsumerWidget {
  const TransactionsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final selectedFilter = ref.watch(transactionFilterProvider);
    final transactionsAsync = ref.watch(transactionsListProvider);
    final dismissedIds = ref.watch(dismissedTransactionsProvider);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ColoredBox(
          color: AppColors.navy,
          child: PageHeader(
            title: 'Transactions',
            bottomPadding: 20,
            bottom: _FilterBar(
              selected: selectedFilter,
              onSelected: (option) =>
                  ref.read(transactionFilterProvider.notifier).select(option),
            ),
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () async {
              ref.invalidate(transactionsListProvider);
              await ref.read(transactionsListProvider.future).catchError(
                    (_) => const TransactionListData.empty(),
              );
            },
            child: transactionsAsync.when(
              skipLoadingOnReload: true,
              data: (data) {
                final visibleTransactions = data.transactions
                    .where((tx) => !dismissedIds.contains(tx.id))
                    .toList();

                return ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(20, 16, 20, 28),
                  children: [
                    _MonthlySummaryCard(summary: data.summary),
                    const SizedBox(height: 18),
                    if (visibleTransactions.isEmpty)
                      const AppCard(
                        child: AsyncSectionMessage(
                          message: 'No transactions found for this wallet.',
                        ),
                      )
                    else
                      ..._buildGroupedSections(
                        context,
                        ref,
                        visibleTransactions,
                      ),
                  ],
                );
              },
              loading: () => ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(20),
                children: const [
                  AppCard(child: AsyncSectionMessage(loading: true)),
                ],
              ),
              error: (error, _) => ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(20),
                children: [
                  AppCard(
                    child: AsyncSectionMessage(
                      message: describeError(error),
                      onRetry: () => ref.invalidate(transactionsListProvider),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }

  List<Widget> _buildGroupedSections(
      BuildContext context,
      WidgetRef ref,
      List<LedgerTransaction> transactions,
      ) {
    final grouped = <String, List<LedgerTransaction>>{};
    final now = DateTime.now();

    for (final tx in transactions) {
      final label = _sectionDateLabel(tx.createdAt, now);
      grouped.putIfAbsent(label, () => []).add(tx);
    }

    final widgets = <Widget>[];
    grouped.forEach((dateLabel, items) {
      widgets.add(
        Padding(
          padding: const EdgeInsets.only(left: 4, bottom: 10, top: 4),
          child: Text(
            dateLabel,
            style: const TextStyle(
              color: AppColors.textMuted,
              fontSize: 13.5,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      );

      for (final tx in items) {
        widgets.add(
          TransactionCard(
            transaction: tx,
            onRemove: () {
              ref.read(dismissedTransactionsProvider.notifier).remove(tx.id);
              final messenger = ScaffoldMessenger.maybeOf(context);
              messenger
                ?..hideCurrentSnackBar()
                ..showSnackBar(
                  SnackBar(
                    content: Text('Removed "${tx.title}" from list'),
                    action: SnackBarAction(
                      label: 'Undo',
                      textColor: AppColors.shuttle,
                      onPressed: () => ref
                          .read(dismissedTransactionsProvider.notifier)
                          .restore(tx.id),
                    ),
                  ),
                );
            },
          ),
        );
      }
      widgets.add(const SizedBox(height: 6));
    });

    return widgets;
  }

  static String _sectionDateLabel(DateTime value, DateTime now) {
    final today = DateLabels.dayOf(now);
    final diff = today.difference(DateLabels.dayOf(value)).inDays;
    if (diff == 0) return 'Today';
    if (diff == 1) return 'Yesterday';
    return DateFormat('EEE, MMM dd').format(value);
  }
}

class _FilterBar extends StatelessWidget {
  const _FilterBar({
    required this.selected,
    required this.onSelected,
  });

  final TransactionFilterOption selected;
  final ValueChanged<TransactionFilterOption> onSelected;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (final option in TransactionFilterOption.values) ...[
            _FilterPill(
              label: option.label,
              selected: option == selected,
              onTap: () => onSelected(option),
            ),
            if (option != TransactionFilterOption.values.last)
              const SizedBox(width: 8),
          ],
        ],
      ),
    );
  }
}

class _FilterPill extends StatelessWidget {
  const _FilterPill({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        decoration: BoxDecoration(
          color: selected ? Colors.white : Colors.white.withValues(alpha: 0.14),
          borderRadius: BorderRadius.circular(20),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: selected ? AppColors.navy : Colors.white,
            fontSize: 13.5,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
    );
  }
}

class _MonthlySummaryCard extends StatelessWidget {
  const _MonthlySummaryCard({required this.summary});

  final MonthlySummary summary;

  @override
  Widget build(BuildContext context) {
    return AppCard(
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Deposited this month',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: AppColors.textMuted,
                    fontSize: 12.5,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  summary.depositedThisMonthFormatted,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: AppColors.success,
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          Container(
            width: 1,
            height: 36,
            margin: const EdgeInsets.symmetric(horizontal: 12),
            color: AppColors.divider,
          ),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Spent this month',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: AppColors.textMuted,
                    fontSize: 12.5,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  summary.spentThisMonthFormatted,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: AppColors.navy,
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}