/// Integer-kuruş money helpers mirroring the backend's `App\Support\Money`.
///
/// All amounts are kept as whole cents (`int`) so client-side previews never
/// suffer from binary floating point drift. Formatting follows the Turkish
/// standard used by the API: `₺1.250,00`, `-₺21,50`.
class Money {
  Money._();

  static const String symbol = '₺';

  static final RegExp _apiPattern = RegExp(r'^(-)?(\d+)(?:\.(\d{1,2}))?$');
  static final RegExp _inputPattern = RegExp(r'^(\d+)(?:[.,](\d{0,2}))?$');

  /// Parses an API decimal string such as `"1250.00"` or `"-21.5"`.
  static int parseApi(Object? raw) {
    if (raw == null) return 0;
    if (raw is int) return raw * 100;
    if (raw is double) return (raw * 100).round();

    final match = _apiPattern.firstMatch(raw.toString().trim());
    if (match == null) return 0;

    final whole = int.parse(match.group(2)!);
    final fraction = (match.group(3) ?? '0').padRight(2, '0');
    final cents = whole * 100 + int.parse(fraction);
    return match.group(1) == '-' ? -cents : cents;
  }

  /// Parses what the student typed (`"250"`, `"250,5"`, `"250.50"`).
  /// Returns `null` for empty or malformed input.
  static int? tryParseInput(String raw) {
    final match = _inputPattern.firstMatch(raw.trim());
    if (match == null) return null;

    final whole = int.parse(match.group(1)!);
    final fraction = (match.group(2) ?? '').padRight(2, '0');
    return whole * 100 + int.parse(fraction.isEmpty ? '0' : fraction);
  }

  /// `125050` -> `"1250.50"` (the wire format the API expects).
  static String toApi(int cents) {
    final negative = cents < 0;
    final abs = cents.abs();
    final value = '${abs ~/ 100}.${(abs % 100).toString().padLeft(2, '0')}';
    return negative ? '-$value' : value;
  }

  /// `125050` -> `"1250,50"`, used to prefill amount text fields.
  static String toInput(int cents) {
    final abs = cents.abs();
    return '${abs ~/ 100},${(abs % 100).toString().padLeft(2, '0')}';
  }

  /// `125000` -> `"₺1.250,00"`; pass [sign] (`+`/`-`) to force a prefix.
  static String format(int cents, {String sign = ''}) {
    final negative = cents < 0;
    final abs = cents.abs();
    final effectiveSign = sign.isNotEmpty ? sign : (negative ? '-' : '');
    return '$effectiveSign$symbol${_group(abs ~/ 100)},'
        '${(abs % 100).toString().padLeft(2, '0')}';
  }

  /// `500000` -> `"₺5.000"` (whole lira, used for chips and limits).
  static String formatWhole(int cents) => '$symbol${_group(cents ~/ 100)}';

  static String _group(int value) {
    final digits = value.toString();
    final buffer = StringBuffer();
    for (var i = 0; i < digits.length; i++) {
      if (i > 0 && (digits.length - i) % 3 == 0) buffer.write('.');
      buffer.write(digits[i]);
    }
    return buffer.toString();
  }
}
