import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/service_style.dart';
import '../../core/utils/money.dart';
import '../../state/wallet_providers.dart';
import 'amount_sheet_parts.dart';

/// Opens the Top up pop-up that moves money Main Wallet → [vendorCode].
Future<void> showTopUpSheet(BuildContext context, String vendorCode) async {
  final messenger = ScaffoldMessenger.of(context);
  final message = await showAmountSheet<String>(
    context,
    (_) => TopUpSheet(vendorCode: vendorCode),
  );
  if (message != null) {
    messenger
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

class TopUpSheet extends ConsumerStatefulWidget {
  const TopUpSheet({super.key, required this.vendorCode});

  final String vendorCode;

  static const quickAmounts = [2500, 5000, 10000];

  @override
  ConsumerState<TopUpSheet> createState() => _TopUpSheetState();
}

class _TopUpSheetState extends ConsumerState<TopUpSheet> {
  final _controller = TextEditingController(text: Money.toInput(5000));
  bool _submitting = false;
  String? _serverError;
  bool _maxSelected = false;

  String? _idempotencyKey;
  int? _keyAmount;

  ServiceStyle get _style => ServiceStyle.of(widget.vendorCode);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  int? get _amount => Money.tryParseInput(_controller.text);

  String? _validationMessage(int available) {
    final amount = _amount;
    if (amount == null || amount == 0) return null;
    if (amount > available) {
      return available == 0
          ? 'Your Main Wallet is empty. Deposit funds first.'
          : 'You only have ${Money.format(available)} in your Main Wallet.';
    }
    return null;
  }

  void _setAmount(int amount, {bool max = false}) {
    setState(() {
      _controller.text = Money.toInput(amount);
      _maxSelected = max;
      _serverError = null;
    });
  }

  Future<void> _submit() async {
    final amount = _amount!;
    if (_keyAmount != amount) {
      _idempotencyKey = LedgerActions.newIdempotencyKey();
      _keyAmount = amount;
    }

    setState(() {
      _submitting = true;
      _serverError = null;
    });
    try {
      final result = await ref.read(ledgerActionsProvider).allocate(
            widget.vendorCode,
            amount,
            idempotencyKey: _idempotencyKey!,
          );
      if (!mounted) return;
      Navigator.of(context).pop(
        'Moved ${Money.format(result.transaction.amount)} to ${_style.label}',
      );
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _serverError = describeError(error);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final wallets = ref.watch(walletsProvider).value;
    final available = wallets?.mainBalance ?? 0;
    final serviceBalance = wallets?.serviceFor(widget.vendorCode)?.balance ?? 0;
    final amount = _amount ?? 0;
    final error = _validationMessage(available);
    final valid = amount > 0 && error == null;
    final style = _style;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SheetHeader(
          title: 'Top up ${style.label}',
          subtitle: 'From Main Wallet · Available ${Money.format(available)}',
        ),
        const SizedBox(height: 22),
        AmountInputField(
          controller: _controller,
          accent: AppColors.primary,
          onChanged: (_) => setState(() {
            _maxSelected = false;
            _serverError = null;
          }),
        ),
        AmountError(_serverError ?? error),
        const SizedBox(height: 14),
        Text(
          '${style.label} balance ${Money.format(serviceBalance)}',
          textAlign: TextAlign.center,
          style: const TextStyle(color: AppColors.textMuted, fontSize: 14),
        ),
        const SizedBox(height: 16),
        Row(
          children: [
            for (final quick in TopUpSheet.quickAmounts) ...[
              Expanded(
                child: QuickAmountChip(
                  label: Money.formatWhole(quick),
                  selected: !_maxSelected && amount == quick,
                  selectedColor: style.color,
                  onTap: _submitting ? null : () => _setAmount(quick),
                ),
              ),
              const SizedBox(width: 10),
            ],
            Expanded(
              child: QuickAmountChip(
                label: 'Max',
                selected: _maxSelected && amount == available,
                selectedColor: style.color,
                onTap: _submitting || available == 0
                    ? null
                    : () => _setAmount(available, max: true),
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        SummaryLine(
          label: 'Main Wallet after',
          value: Money.format(available - (valid ? amount : 0)),
        ),
        const Divider(height: 1, color: AppColors.divider),
        SummaryLine(
          label: '${style.label} after',
          value: Money.format(serviceBalance + (valid ? amount : 0)),
          emphasized: true,
        ),
        const SizedBox(height: 8),
        Text(
          'Funds become locked to ${style.label} and can\'t be moved to '
          'another service.',
          textAlign: TextAlign.center,
          style: const TextStyle(color: AppColors.textMuted, fontSize: 13.5),
        ),
        const SizedBox(height: 18),
        FilledButton(
          onPressed: valid && !_submitting ? _submit : null,
          style: FilledButton.styleFrom(backgroundColor: style.color),
          child: _submitting
              ? const SizedBox(
                  width: 24,
                  height: 24,
                  child: CircularProgressIndicator(
                    color: Colors.white,
                    strokeWidth: 2.5,
                  ),
                )
              : Text('Top up ${style.label}'),
        ),
      ],
    );
  }
}
