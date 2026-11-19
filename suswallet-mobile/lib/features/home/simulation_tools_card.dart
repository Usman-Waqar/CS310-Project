import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/service_style.dart';
import '../../core/utils/money.dart';
import '../../state/wallet_providers.dart';
import '../../widgets/common.dart';
import '../transfers/amount_sheet_parts.dart';

/// Expandable "Simulation tools" panel on Home: a virtual SUCard tap with a
/// typed amount (−/+ change it by ₺1) and "Refund last tap".
class SimulationToolsCard extends ConsumerStatefulWidget {
  const SimulationToolsCard({super.key});

  @override
  ConsumerState<SimulationToolsCard> createState() =>
      _SimulationToolsCardState();
}

class _SimulationToolsCardState extends ConsumerState<SimulationToolsCard> {
  static const step = 100; // ₺1,00 per arrow press
  static const minAmount = 1; // ₺0,01 (backend minimum)
  static const maxAmount = 500000; // ₺5.000,00 (backend maximum)

  final _amountController = TextEditingController();

  bool _expanded = false;
  ServiceStyle _service = ServiceStyle.shuttle;
  bool _busy = false;

  /// Key of the tap that failed, reused on retry so a lost response can never
  /// charge twice. Cleared once the amount or service changes.
  String? _pendingTapKey;

  @override
  void initState() {
    super.initState();
    _amountController.text = Money.toInput(_defaultPriceFor(_service));
  }

  @override
  void dispose() {
    _amountController.dispose();
    super.dispose();
  }

  int _defaultPriceFor(ServiceStyle service) {
    final wallet = ref.read(walletsProvider).value?.serviceFor(service.code);
    return wallet?.vendor?.defaultTapPrice ??
        switch (service.code) {
          'SHUTTLE' => 2150,
          'CAFETERIA' => 9600,
          _ => 800,
        };
  }

  /// Typed amount in kuruş, or `null` while the field is empty or malformed.
  int? get _amount => Money.tryParseInput(_amountController.text);

  String? get _amountError {
    final amount = _amount;
    if (amount == null) return 'Enter an amount.';
    if (amount < minAmount) return 'Amount must be more than ₺0.';
    if (amount > maxAmount) {
      return 'A single tap can be at most ${Money.formatWhole(maxAmount)}.';
    }
    return null;
  }

  void _setAmount(int cents) {
    final text = Money.toInput(cents);
    _amountController.value = TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
    _onAmountEdited();
  }

  void _onAmountEdited() => setState(() => _pendingTapKey = null);

  void _selectService(ServiceStyle service) {
    if (service.code == _service.code) return;
    _service = service;
    _setAmount(_defaultPriceFor(service));
  }

  void _stepAmount(int delta) {
    final current = _amount ?? 0;
    _setAmount((current + delta).clamp(minAmount, maxAmount));
  }

  Future<void> _tapCard() async {
    final amount = _amount;
    if (amount == null || _amountError != null) return;

    FocusScope.of(context).unfocus();
    final key = _pendingTapKey ??= LedgerActions.newIdempotencyKey();
    setState(() => _busy = true);
    try {
      final result = await ref
          .read(ledgerActionsProvider)
          .tap(_service.code, amount, idempotencyKey: key);
      _pendingTapKey = null;
      if (!mounted) return;
      showAppSnack(
        context,
        'Card tapped: ${result.transaction.amountFormatted} '
        'from ${_service.label}',
      );
    } catch (error) {
      // A rejected request (4xx) is final, so the next try gets a new key.
      if (error is ApiException && error.statusCode != null) {
        _pendingTapKey = null;
      }
      if (mounted) showAppSnack(context, describeError(error), error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _refundLastTap() async {
    FocusScope.of(context).unfocus();
    setState(() => _busy = true);
    try {
      final result = await ref
          .read(ledgerActionsProvider)
          .refundLastTap(idempotencyKey: LedgerActions.newIdempotencyKey());
      if (!mounted) return;
      final style = ServiceStyle.of(result.transaction.vendorCode);
      showAppSnack(
        context,
        'Refunded ${result.transaction.amountFormatted} to ${style.label}',
      );
    } catch (error) {
      if (mounted) showAppSnack(context, describeError(error), error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AppCard(
      padding: EdgeInsets.zero,
      child: Column(
        children: [
          InkWell(
            borderRadius: BorderRadius.circular(20),
            onTap: () => setState(() => _expanded = !_expanded),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(18, 16, 14, 16),
              child: Row(
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: const BoxDecoration(
                      color: AppColors.primarySoft,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.smartphone_outlined,
                      color: AppColors.primary,
                    ),
                  ),
                  const SizedBox(width: 14),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Simulation tools',
                          style: TextStyle(
                            fontSize: 17,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        Text(
                          'Test card taps and refunds',
                          style: TextStyle(
                            color: AppColors.textMuted,
                            fontSize: 13.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                  AnimatedRotation(
                    turns: _expanded ? 0.5 : 0,
                    duration: const Duration(milliseconds: 200),
                    child: const Icon(
                      Icons.keyboard_arrow_down_rounded,
                      color: AppColors.textMuted,
                      size: 28,
                    ),
                  ),
                ],
              ),
            ),
          ),
          AnimatedSize(
            duration: const Duration(milliseconds: 220),
            curve: Curves.easeOut,
            alignment: Alignment.topCenter,
            child: _expanded
                ? _buildPanel()
                : const SizedBox(width: double.infinity),
          ),
        ],
      ),
    );
  }

  Widget _buildPanel() {
    final balance =
        ref.watch(walletsProvider).value?.serviceFor(_service.code)?.balance;
    final amount = _amount;
    final error = _amountError;

    return Column(
      children: [
        const Divider(height: 1, color: AppColors.divider),
        Padding(
          padding: const EdgeInsets.fromLTRB(18, 18, 18, 20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _ServiceSegmentedControl(
                selected: _service,
                onChanged: _busy ? null : _selectService,
              ),
              const SizedBox(height: 18),
              Row(
                children: [
                  _StepButton(
                    icon: Icons.remove,
                    tooltip: 'Decrease by ₺1',
                    onPressed: _busy || amount == null || amount <= minAmount
                        ? null
                        : () => _stepAmount(-step),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: AmountInputField(
                      controller: _amountController,
                      accent: _service.color,
                      fontSize: 28,
                      verticalPadding: 12,
                      enabled: !_busy,
                      onChanged: (_) => _onAmountEdited(),
                    ),
                  ),
                  const SizedBox(width: 10),
                  _StepButton(
                    icon: Icons.add,
                    tooltip: 'Increase by ₺1',
                    onPressed: _busy || (amount ?? 0) >= maxAmount
                        ? null
                        : () => _stepAmount(step),
                  ),
                ],
              ),
              AmountError(error),
              if (balance != null)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(
                    '${_service.label} balance ${Money.format(balance)}',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: AppColors.textMuted,
                      fontSize: 13,
                    ),
                  ),
                ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: _busy || error != null ? null : _tapCard,
                style: FilledButton.styleFrom(
                  backgroundColor: _service.color,
                ),
                icon: _busy
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          color: Colors.white,
                          strokeWidth: 2.5,
                        ),
                      )
                    : const Icon(Icons.smartphone_outlined),
                label: const Text('Tap card'),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: _busy ? null : _refundLastTap,
                icon: const Icon(Icons.replay),
                label: const Text('Refund last tap'),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// Round −/+ button beside the amount field.
class _StepButton extends StatelessWidget {
  const _StepButton({
    required this.icon,
    required this.tooltip,
    required this.onPressed,
  });

  final IconData icon;
  final String tooltip;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return IconButton.filled(
      onPressed: onPressed,
      tooltip: tooltip,
      iconSize: 24,
      style: IconButton.styleFrom(
        backgroundColor: AppColors.field,
        foregroundColor: AppColors.navy,
        disabledBackgroundColor: AppColors.field,
        disabledForegroundColor: AppColors.textSubtle,
        minimumSize: const Size(48, 48),
      ),
      icon: Icon(icon),
    );
  }
}

class _ServiceSegmentedControl extends StatelessWidget {
  const _ServiceSegmentedControl({
    required this.selected,
    required this.onChanged,
  });

  final ServiceStyle selected;
  final ValueChanged<ServiceStyle>? onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(5),
      decoration: BoxDecoration(
        color: AppColors.field,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          for (final service in ServiceStyle.services)
            Expanded(
              child: Semantics(
                selected: service.code == selected.code,
                button: true,
                child: GestureDetector(
                  onTap: onChanged == null ? null : () => onChanged!(service),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 180),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    decoration: BoxDecoration(
                      color: service.code == selected.code
                          ? service.color
                          : Colors.transparent,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      service.label,
                      style: TextStyle(
                        color: service.code == selected.code
                            ? Colors.white
                            : AppColors.navy,
                        fontSize: 14.5,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
