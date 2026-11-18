import 'package:flutter_test/flutter_test.dart';
import 'package:suswallet_mobile/core/utils/money.dart';

void main() {
  group('Money.format (matches backend Money::formatTry)', () {
    test('formats with Turkish separators', () {
      expect(Money.format(125000), '₺1.250,00');
      expect(Money.format(8550), '₺85,50');
      expect(Money.format(0), '₺0,00');
      expect(Money.format(123456789), '₺1.234.567,89');
    });

    test('applies explicit and negative signs', () {
      expect(Money.format(2150, sign: '-'), '-₺21,50');
      expect(Money.format(174100, sign: '+'), '+₺1.741,00');
      expect(Money.format(-400), '-₺4,00');
    });

    test('formats whole lira for chips', () {
      expect(Money.formatWhole(500000), '₺5.000');
      expect(Money.formatWhole(5000), '₺50');
    });
  });

  group('Money.parseApi', () {
    test('parses API decimal strings into kuruş', () {
      expect(Money.parseApi('1250.00'), 125000);
      expect(Money.parseApi('21.5'), 2150);
      expect(Money.parseApi('-4.00'), -400);
      expect(Money.parseApi('0.01'), 1);
      expect(Money.parseApi(null), 0);
      expect(Money.parseApi('garbage'), 0);
    });
  });

  group('Money.tryParseInput', () {
    test('accepts comma or dot decimals', () {
      expect(Money.tryParseInput('250'), 25000);
      expect(Money.tryParseInput('250,5'), 25050);
      expect(Money.tryParseInput('250.50'), 25050);
      expect(Money.tryParseInput('10,'), 1000);
    });

    test('rejects malformed input', () {
      expect(Money.tryParseInput(''), isNull);
      expect(Money.tryParseInput('1.250,00'), isNull);
      expect(Money.tryParseInput('12.345'), isNull);
      expect(Money.tryParseInput('abc'), isNull);
    });

    test('round-trips through toInput and toApi', () {
      expect(Money.toInput(125050), '1250,50');
      expect(Money.tryParseInput(Money.toInput(125050)), 125050);
      expect(Money.toApi(125050), '1250.50');
      expect(Money.toApi(5), '0.05');
    });
  });
}
