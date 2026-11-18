import 'package:intl/intl.dart';

/// Time labels used by the Home recent transactions list.
class DateLabels {
  DateLabels._();

  static final DateFormat _time = DateFormat('HH:mm');
  static final DateFormat _weekday = DateFormat('EEE');
  static final DateFormat _shortDate = DateFormat('MMM dd');

  static DateTime dayOf(DateTime value) =>
      DateTime(value.year, value.month, value.day);

  static String time(DateTime value) => _time.format(value);

  /// "Today 08:15", "Yesterday 18:20", "Mon 18:30" (this week) or
  /// "Nov 02 14:12".
  static String relative(DateTime value, {DateTime? now}) {
    final today = dayOf(now ?? DateTime.now());
    final difference = today.difference(dayOf(value)).inDays;
    if (difference == 0) return 'Today ${time(value)}';
    if (difference == 1) return 'Yesterday ${time(value)}';
    if (difference > 1 && difference < 7) {
      return '${_weekday.format(value)} ${time(value)}';
    }
    return '${_shortDate.format(value)} ${time(value)}';
  }
}
