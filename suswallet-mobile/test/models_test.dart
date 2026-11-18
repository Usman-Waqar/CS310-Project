import 'package:flutter_test/flutter_test.dart';
import 'package:suswallet_mobile/models/transaction.dart';
import 'package:suswallet_mobile/models/user_profile.dart';
import 'package:suswallet_mobile/models/wallet.dart';

import 'test_fixtures.dart';

void main() {
  test('UserProfile parses and round-trips the profile resource', () {
    final user = UserProfile.fromJson(userJson());
    expect(user.studentId, '00031234');
    expect(user.initials, 'SN');
    expect(user.maskedPhone, '+90 5** *** ** 12');
    expect(user.resolvedAvatarUrl, isNull);

    final copy = UserProfile.fromJson(user.toJson());
    expect(copy.email, user.email);
    expect(copy.fullName, user.fullName);
  });

  test('UserProfile resolves relative avatar paths against the API origin', () {
    final user = UserProfile.fromJson({
      ...userJson(),
      'avatar_path': '/storage/avatars/me.jpg',
      'avatar_url': 'http://localhost:8000/storage/avatars/me.jpg',
    });
    expect(user.resolvedAvatarUrl, endsWith(':8000/storage/avatars/me.jpg'));
  });

  test('WalletBundle parses main and service wallets', () {
    final bundle = WalletBundle.fromJson(walletsJson());
    expect(bundle.mainBalance, 125000);
    expect(bundle.services, hasLength(3));
    expect(bundle.serviceFor('SHUTTLE')?.balance, 8550);
    expect(bundle.serviceFor('PRINTING')?.vendor?.defaultTapPrice, 800);
    expect(bundle.serviceFor('UNKNOWN'), isNull);
  });

  test('LedgerTransaction parses the transaction resource', () {
    final tap = LedgerTransaction.fromJson(
      transactionJson(createdAt: '2026-11-14T16:15:00+03:00'),
    );
    expect(tap.isCardTap, isTrue);
    expect(tap.amount, 2150);
    expect(tap.amountFormatted, '-₺21,50');
    expect(tap.createdAt.toUtc(), DateTime.utc(2026, 11, 14, 13, 15));

    final allocation = LedgerTransaction.fromJson(transactionJson(
      type: 'ALLOCATION',
      amount: '50.00',
      sign: '',
      vendor: 'PRINTING',
      title: 'Allocated to Printing',
    ));
    expect(allocation.isAllocation, isTrue);
    expect(allocation.sign, isEmpty);
  });

  test('LedgerResult parses mutation responses', () {
    final result = LedgerResult.fromJson({
      'message': 'Deposit completed.',
      'idempotent_replay': false,
      'data': {
        'transaction': transactionJson(
          type: 'DEPOSIT',
          amount: '250.00',
          sign: '+',
          vendor: 'MAIN',
          title: 'Card deposit',
        ),
        'wallets': walletsJson(main: '1500.00'),
      },
    });

    expect(result.replayed, isFalse);
    expect(result.transaction.isDeposit, isTrue);
    expect(result.wallets.mainBalance, 150000);
  });
}
