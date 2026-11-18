import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/service_style.dart';
import '../../core/utils/money.dart';
import '../../state/wallet_providers.dart';
import '../../widgets/common.dart';
import '../transfers/deposit_sheet.dart';
import '../transfers/top_up_sheet.dart';

class ServicesScreen extends ConsumerWidget {
  const ServicesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final walletsAsync = ref.watch(walletsProvider);
    final wallets = walletsAsync.value;

    return RefreshIndicator(
      onRefresh: () => ref.read(ledgerActionsProvider).refreshAll(),
      edgeOffset: MediaQuery.paddingOf(context).top,
      child: ListView(
        padding: EdgeInsets.zero,
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          OverlapHeader(
            overlap: 28,
            header: const PageHeader(
              title: 'Services',
              subtitle: 'Top up your campus wallets',
              bottomPadding: 20,
            ),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: _WalletCard(
                style: ServiceStyle.main,
                title: 'Main Wallet',
                subtitle: 'Unallocated · available to allocate',
                titleColor: AppColors.navy,
                balance: wallets?.main?.balance,
                actionLabel: 'Deposit',
                onAction: () => showDepositSheet(context),
              ),
            ),
          ),
          if (walletsAsync.hasError && wallets == null)
            AsyncSectionMessage(
              message: describeError(walletsAsync.error!),
              onRetry: () => ref.invalidate(walletsProvider),
            ),
          for (final style in ServiceStyle.services)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
              child: _WalletCard(
                style: style,
                title: style.label,
                subtitle:
                    'Locked to ${style.label.toLowerCase()} vendor',
                titleColor: style.color,
                balance: wallets?.serviceFor(style.code)?.balance,
                actionLabel: 'Top up',
                onAction: () => showTopUpSheet(context, style.code),
              ),
            ),
          const Padding(
            padding: EdgeInsets.fromLTRB(16, 20, 16, 24),
            child: InfoNote(
              'Funds moved from the Main Wallet to a service are locked to '
              'that vendor and can\'t be transferred to another service.',
            ),
          ),
        ],
      ),
    );
  }
}

class _WalletCard extends StatelessWidget {
  const _WalletCard({
    required this.style,
    required this.title,
    required this.subtitle,
    required this.titleColor,
    required this.balance,
    required this.actionLabel,
    required this.onAction,
  });

  final ServiceStyle style;
  final String title;
  final String subtitle;
  final Color titleColor;
  final int? balance;
  final String actionLabel;
  final VoidCallback onAction;

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        AppCard(
          padding: const EdgeInsets.fromLTRB(22, 20, 20, 20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.only(right: 48),
                child: Text(
                  title,
                  style: TextStyle(
                    color: titleColor,
                    fontSize: 21,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: const TextStyle(
                  color: AppColors.textMuted,
                  fontSize: 13.5,
                ),
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: FittedBox(
                      alignment: Alignment.centerLeft,
                      fit: BoxFit.scaleDown,
                      child: Text(
                        balance == null ? '—' : Money.format(balance!),
                        style: const TextStyle(
                          fontSize: 32,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  FilledButton.icon(
                    onPressed: onAction,
                    icon: const Icon(Icons.add, size: 20),
                    label: Text(actionLabel),
                    style: FilledButton.styleFrom(
                      backgroundColor: style.color,
                      minimumSize: const Size(0, 48),
                      padding: const EdgeInsets.symmetric(horizontal: 20),
                      shape: const StadiumBorder(),
                      textStyle: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        // Coloured corner badge with the service icon.
        Positioned(
          top: 0,
          right: 0,
          child: Container(
            width: 58,
            height: 58,
            decoration: BoxDecoration(
              color: style.color,
              borderRadius: const BorderRadius.only(
                topRight: Radius.circular(20),
                bottomLeft: Radius.circular(58),
              ),
            ),
            alignment: const Alignment(0.35, -0.35),
            child: Icon(style.icon, color: Colors.white, size: 26),
          ),
        ),
      ],
    );
  }
}
