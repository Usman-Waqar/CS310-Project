import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/theme/app_colors.dart';

/// Opens a keyboard-aware bottom sheet above the bottom navigation bar.
Future<T?> showAmountSheet<T>(BuildContext context, WidgetBuilder builder) {
  return showModalBottomSheet<T>(
    context: context,
    useRootNavigator: true,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (sheetContext) => Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.viewInsetsOf(sheetContext).bottom,
      ),
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
        child: builder(sheetContext),
      ),
    ),
  );
}

/// Title row with subtitle and a round close (X) button.
class SheetHeader extends StatelessWidget {
  const SheetHeader({super.key, required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  fontSize: 23,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                subtitle,
                style: const TextStyle(
                  color: AppColors.textMuted,
                  fontSize: 14,
                ),
              ),
            ],
          ),
        ),
        IconButton.filled(
          onPressed: () => Navigator.of(context).pop(),
          tooltip: 'Close',
          style: IconButton.styleFrom(
            backgroundColor: AppColors.field,
            foregroundColor: AppColors.navy,
          ),
          icon: const Icon(Icons.close),
        ),
      ],
    );
  }
}

/// Large centred "₺250,00" amount field with an accent border. The ₺ sign
/// stays attached to the digits, as in the wireframe.
class AmountInputField extends StatefulWidget {
  const AmountInputField({
    super.key,
    required this.controller,
    required this.accent,
    this.autofocus = false,
    this.onChanged,
    this.fontSize = 38,
    this.verticalPadding = 20,
    this.enabled = true,
  });

  final TextEditingController controller;
  final Color accent;
  final bool autofocus;
  final ValueChanged<String>? onChanged;
  final double fontSize;
  final double verticalPadding;
  final bool enabled;

  @override
  State<AmountInputField> createState() => _AmountInputFieldState();
}

class _AmountInputFieldState extends State<AmountInputField> {
  final _focusNode = FocusNode();

  @override
  void dispose() {
    _focusNode.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final amountStyle = TextStyle(
      fontSize: widget.fontSize,
      fontWeight: FontWeight.w700,
      color: AppColors.navy,
    );

    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: widget.enabled ? _focusNode.requestFocus : null,
      child: Container(
        padding: EdgeInsets.symmetric(
          vertical: widget.verticalPadding,
          horizontal: 16,
        ),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: widget.accent, width: 1.8),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text('₺', style: amountStyle),
            Flexible(
              child: IntrinsicWidth(
                child: TextField(
                  controller: widget.controller,
                  focusNode: _focusNode,
                  autofocus: widget.autofocus,
                  enabled: widget.enabled,
                  onChanged: widget.onChanged,
                  keyboardType:
                      const TextInputType.numberWithOptions(decimal: true),
                  inputFormatters: [_AmountInputFormatter()],
                  cursorColor: widget.accent,
                  style: amountStyle,
                  // Override every themed border/fill: the outer container
                  // draws the accent border.
                  decoration: InputDecoration(
                    hintText: '0,00',
                    hintStyle: amountStyle.copyWith(
                      color: AppColors.textSubtle,
                    ),
                    filled: false,
                    isDense: true,
                    contentPadding: EdgeInsets.zero,
                    border: InputBorder.none,
                    enabledBorder: InputBorder.none,
                    focusedBorder: InputBorder.none,
                    disabledBorder: InputBorder.none,
                    hoverColor: Colors.transparent,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Allows up to 7 whole digits and 2 decimals, using `,` or `.`.
class _AmountInputFormatter extends TextInputFormatter {
  static final _pattern = RegExp(r'^\d{0,7}([.,]\d{0,2})?$');

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    return _pattern.hasMatch(newValue.text) ? newValue : oldValue;
  }
}

/// Quick amount chip (₺50, ₺100, Max ...).
class QuickAmountChip extends StatelessWidget {
  const QuickAmountChip({
    super.key,
    required this.label,
    required this.selected,
    required this.selectedColor,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final Color selectedColor;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: selected,
      child: Material(
        color: selected ? selectedColor : AppColors.field,
        shape: const StadiumBorder(),
        child: InkWell(
          customBorder: const StadiumBorder(),
          onTap: onTap,
          child: Container(
            height: 50,
            alignment: Alignment.center,
            child: Text(
              label,
              style: TextStyle(
                color: selected ? Colors.white : AppColors.navy,
                fontSize: 16,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// "Label ............ value" summary line under the chips.
class SummaryLine extends StatelessWidget {
  const SummaryLine({
    super.key,
    required this.label,
    required this.value,
    this.emphasized = false,
  });

  final String label;
  final String value;
  final bool emphasized;

  @override
  Widget build(BuildContext context) {
    final style = TextStyle(
      fontSize: emphasized ? 16.5 : 16,
      fontWeight: emphasized ? FontWeight.w700 : FontWeight.w400,
      color: AppColors.navy,
    );
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Row(
        children: [
          Expanded(child: Text(label, style: style)),
          Text(
            value,
            style: style.copyWith(
              fontWeight: emphasized ? FontWeight.w700 : FontWeight.w500,
              fontSize: emphasized ? 19 : 17,
            ),
          ),
        ],
      ),
    );
  }
}

/// Small red helper text shown when the typed amount is invalid.
class AmountError extends StatelessWidget {
  const AmountError(this.message, {super.key});

  final String? message;

  @override
  Widget build(BuildContext context) {
    if (message == null) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Text(
        message!,
        textAlign: TextAlign.center,
        style: const TextStyle(color: AppColors.danger, fontSize: 13),
      ),
    );
  }
}
