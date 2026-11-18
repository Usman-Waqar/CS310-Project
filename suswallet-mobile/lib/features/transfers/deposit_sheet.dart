import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/money.dart';
import '../../state/wallet_providers.dart';
import 'amount_sheet_parts.dart';

/// Opens the Deposit pop-up (simulated card top-up into the Main Wallet).
Future<void> showDepositSheet(BuildContext context) async {
  final messenger = ScaffoldMessenger.of(context);
  final message = await showAmountSheet<String>(
    context,
    (_) => const DepositSheet(),
  );
  if (message != null) {
    messenger
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

class DepositSheet extends ConsumerStatefulWidget {
  const DepositSheet({super.key});

  static const minAmount = 1000; // ₺10
  static const maxAmount = 500000; // ₺5.000
  static const quickAmounts = [5000, 10000, 25000, 50000];

  @override
  ConsumerState<DepositSheet> createState() => _DepositSheetState();
}

class _DepositSheetState extends ConsumerState<DepositSheet> {
  final _controller = TextEditingController(text: Money.toInput(25000));
  bool _submitting = false;
  String? _serverError;

  // Reused when retrying the same amount, so a request that reached the
  // server before a network drop is replayed instead of charged twice.
  String? _idempotencyKey;
  int? _keyAmount;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  int? get _amount => Money.tryParseInput(_controller.text);

  String? get _validationMessage {
    final amount = _amount;
    if (amount == null || amount == 0) return null;
    if (amount < DepositSheet.minAmount) {
      return 'Minimum deposit is ${Money.formatWhole(DepositSheet.minAmount)}.';
    }
    if (amount > DepositSheet.maxAmount) {
      return 'Maximum deposit is ${Money.formatWhole(DepositSheet.maxAmount)}.';
    }
    return null;
  }

  bool get _isValid =>
      _amount != null && _amount! > 0 && _validationMessage == null;

  void _selectQuick(int amount) {
    setState(() {
      _controller.text = Money.toInput(amount);
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
      final result = await ref
          .read(ledgerActionsProvider)
          .deposit(amount, idempotencyKey: _idempotencyKey!);
      if (!mounted) return;
      Navigator.of(context).pop(
        'Deposited ${result.transaction.amountFormatted} to Main Wallet',
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
    final balance = ref.watch(walletsProvider).value?.mainBalance ?? 0;
    final amount = _amount ?? 0;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SheetHeader(
          title: 'Deposit',
          subtitle: 'to Main Wallet · Balance ${Money.format(balance)}',
        ),
        const SizedBox(height: 22),
        AmountInputField(
          controller: _controller,
          accent: AppColors.primary,
          onChanged: (_) => setState(() => _serverError = null),
        ),
        AmountError(_serverError ?? _validationMessage),
        const SizedBox(height: 14),
        Text(
          'Min ${Money.formatWhole(DepositSheet.minAmount)} · '
          'Max ${Money.formatWhole(DepositSheet.maxAmount)} · Simulated payment',
          textAlign: TextAlign.center,
          style: const TextStyle(color: AppColors.textMuted, fontSize: 13.5),
        ),
        const SizedBox(height: 16),
        Row(
          children: [
            for (final (index, quick) in DepositSheet.quickAmounts.indexed) ...[
              if (index > 0) const SizedBox(width: 10),
              Expanded(
                child: QuickAmountChip(
                  label: Money.formatWhole(quick),
                  selected: amount == quick,
                  selectedColor: AppColors.navy,
                  onTap: _submitting ? null : () => _selectQuick(quick),
                ),
              ),
            ],
          ],
        ),
        const SizedBox(height: 10),
        SummaryLine(label: 'Deposit amount', value: Money.format(amount)),
        const Divider(height: 1, color: AppColors.divider),
        SummaryLine(
          label: 'New Main Wallet balance',
          value: Money.format(balance + (_isValid ? amount : 0)),
          emphasized: true,
        ),
        const SizedBox(height: 16),
        FilledButton(
          onPressed: _isValid && !_submitting ? _submit : null,
          style: FilledButton.styleFrom(backgroundColor: AppColors.navy),
          child: _submitting
              ? const SizedBox(
                  width: 24,
                  height: 24,
                  child: CircularProgressIndicator(
                    color: Colors.white,
                    strokeWidth: 2.5,
                  ),
                )
              : Text('Deposit ${Money.format(amount)}'),
        ),
      ],
    );
  }
}
