import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/service_style.dart';
import '../../core/utils/money.dart';
import '../../models/wallet.dart';
import '../../state/session_controller.dart';
import '../../state/shell_tab.dart';
import '../../state/wallet_providers.dart';
import '../../widgets/common.dart';
import '../transfers/deposit_sheet.dart';
import '../transfers/top_up_sheet.dart';
import 'recent_transaction_tile.dart';
import 'simulation_tools_card.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  Future<void> _refresh(WidgetRef ref) async {
    await Future.wait([
      ref.read(ledgerActionsProvider).refreshAll(),
      ref.read(sessionProvider.notifier).refreshProfile().catchError((_) {}),
    ]);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(sessionProvider).value;
    final walletsAsync = ref.watch(walletsProvider);
    final wallets = walletsAsync.value ?? const WalletBundle.empty();
    final top = MediaQuery.paddingOf(context).top;

    return RefreshIndicator(
      onRefresh: () => _refresh(ref),
      edgeOffset: top,
      child: ListView(
        padding: EdgeInsets.zero,
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          OverlapHeader(
            overlap: 58,
            header: Padding(
              padding: EdgeInsets.fromLTRB(20, top + 20, 20, 22),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      UserAvatar(user: user, size: 54),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              user?.fullName ?? '',
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 19,
                                height: 1.25,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                            Text(
                              'Student ID ${user?.studentId ?? ''}',
                              style: TextStyle(
                                color: Colors.white.withValues(alpha: 0.85),
                                fontSize: 14,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),
                      StatusPill(
                        label: 'SUCard ${user?.sucardStatus ?? 'Active'}',
                        active: user?.isActive ?? true,
                      ),
                    ],
                  ),
                  const SizedBox(height: 26),
                  Text(
                    'Main Wallet · Unallocated',
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.85),
                      fontSize: 14.5,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Expanded(
                        child: FittedBox(
                          alignment: Alignment.centerLeft,
                          fit: BoxFit.scaleDown,
                          child: walletsAsync.isLoading && walletsAsync.value == null
                              ? const SizedBox(
                                  height: 48,
                                  width: 48,
                                  child: Padding(
                                    padding: EdgeInsets.all(12),
                                    child: CircularProgressIndicator(
                                      color: Colors.white,
                                      strokeWidth: 2.5,
                                    ),
                                  ),
                                )
                              : Text(
                                  Money.format(wallets.mainBalance),
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 40,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      _DepositPill(onPressed: () => showDepositSheet(context)),
                    ],
                  ),
                ],
              ),
            ),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(
                children: [
                  for (final (index, style) in ServiceStyle.services.indexed) ...[
                    if (index > 0) const SizedBox(width: 10),
                    Expanded(
                      child: _ServiceTile(
                        style: style,
                        balance: wallets.serviceFor(style.code)?.balance,
                        onTopUp: () => showTopUpSheet(context, style.code),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
          if (walletsAsync.hasError && walletsAsync.value == null)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
              child: AsyncSectionMessage(
                message: describeError(walletsAsync.error!),
                onRetry: () => ref.invalidate(walletsProvider),
              ),
            ),
          const Padding(
            padding: EdgeInsets.fromLTRB(16, 16, 16, 0),
            child: SimulationToolsCard(),
          ),
          const Padding(
            padding: EdgeInsets.fromLTRB(16, 16, 16, 24),
            child: _RecentTransactionsCard(),
          ),
        ],
      ),
    );
  }
}

class _DepositPill extends StatelessWidget {
  const _DepositPill({required this.onPressed});

  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return FilledButton.icon(
      onPressed: onPressed,
      icon: const Icon(Icons.add, size: 20),
      label: const Text('Deposit'),
      style: FilledButton.styleFrom(
        backgroundColor: Colors.white,
        foregroundColor: AppColors.navy,
        minimumSize: const Size(0, 48),
        padding: const EdgeInsets.symmetric(horizontal: 20),
        shape: const StadiumBorder(),
        textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
      ),
    );
  }
}

/// Coloured Shuttle / Cafeteria / Printing tile with a "+" top-up button.
class _ServiceTile extends StatelessWidget {
  const _ServiceTile({
    required this.style,
    required this.balance,
    required this.onTopUp,
  });

  final ServiceStyle style;
  final int? balance;
  final VoidCallback onTopUp;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: '${style.label} wallet, '
          '${balance == null ? 'loading' : Money.format(balance!)}. Top up',
      excludeSemantics: true,
      child: Material(
        color: style.color,
        borderRadius: BorderRadius.circular(18),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTopUp,
          child: SizedBox(
            height: 120,
            child: Stack(
              children: [
                Positioned(
                  left: 0,
                  top: 0,
                  child: Container(
                    width: 58,
                    height: 50,
                    decoration: const BoxDecoration(
                      color: Colors.white,
                      borderRadius:
                          BorderRadius.only(bottomRight: Radius.circular(48)),
                    ),
                    padding: const EdgeInsets.only(left: 12, top: 10),
                    alignment: Alignment.topLeft,
                    child: Icon(style.icon, color: style.color, size: 26),
                  ),
                ),
                Positioned(
                  right: 10,
                  top: 10,
                  child: Container(
                    width: 30,
                    height: 30,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.3),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(Icons.add, color: Colors.white, size: 20),
                  ),
                ),
                Positioned(
                  right: 12,
                  left: 8,
                  bottom: 12,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        style.label,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      FittedBox(
                        fit: BoxFit.scaleDown,
                        child: Text(
                          balance == null ? '—' : Money.format(balance!),
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 21,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _RecentTransactionsCard extends ConsumerWidget {
  const _RecentTransactionsCard();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final recent = ref.watch(recentTransactionsProvider);

    return AppCard(
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Recent Transactions',
                  style: TextStyle(
                    color: AppColors.primary,
                    fontSize: 19,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              TextButton(
                onPressed: () => ref
                    .read(shellTabProvider.notifier)
                    .select(ShellTab.transactions),
                child: const Text(
                  'See all',
                  style: TextStyle(
                    color: AppColors.navy,
                    decoration: TextDecoration.underline,
                  ),
                ),
              ),
            ],
          ),
          recent.when(
            loading: () => const AsyncSectionMessage(loading: true),
            error: (error, _) => AsyncSectionMessage(
              message: describeError(error),
              onRetry: () => ref.invalidate(recentTransactionsProvider),
            ),
            data: (transactions) => transactions.isEmpty
                ? const AsyncSectionMessage(
                    message: 'No transactions yet. Deposit to get started.',
                  )
                : Column(
                    children: [
                      for (final tx in transactions)
                        RecentTransactionTile(transaction: tx),
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}
