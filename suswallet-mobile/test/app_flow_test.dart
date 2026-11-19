import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:suswallet_mobile/app.dart';
import 'package:suswallet_mobile/core/api/api_exception.dart';
import 'package:suswallet_mobile/core/storage/session_storage.dart';
import 'package:suswallet_mobile/data/suswallet_repository.dart';
import 'package:suswallet_mobile/features/auth/login_screen.dart';
import 'package:suswallet_mobile/models/transaction.dart';
import 'package:suswallet_mobile/models/user_profile.dart';
import 'package:suswallet_mobile/models/wallet.dart';

import 'test_fixtures.dart';

/// In-memory stand-in for the Laravel API.
class FakeRepository implements SusWalletRepository {
  int mainBalance = 125000;
  String fullName = 'Student Name';
  String? phone = '+905321234512';
  final deposits = <int>[];
  final allocations = <(String, int)>[];

  WalletBundle get _wallets =>
      WalletBundle.fromJson(walletsJson(main: _api(mainBalance)));

  static String _api(int cents) =>
      '${cents ~/ 100}.${(cents % 100).toString().padLeft(2, '0')}';

  @override
  Future<LoginResult> login({
    required String email,
    required String password,
    String deviceName = '',
  }) async {
    if (password != 'password123') {
      throw const ApiException(
        message: 'Invalid university email or password.',
        statusCode: 401,
        errorCode: 'INVALID_CREDENTIALS',
      );
    }
    return LoginResult(
      token: 'test-token',
      user: UserProfile.fromJson(userJson(fullName: fullName)),
    );
  }

  @override
  Future<UserProfile> me() async =>
      UserProfile.fromJson(userJson(fullName: fullName));

  @override
  Future<void> logout() async {}

  @override
  Future<UserProfile> updateProfile({
    required String fullName,
    String? phone,
  }) async {
    this.fullName = fullName.trim();
    this.phone = phone;
    return UserProfile.fromJson(userJson(fullName: this.fullName));
  }

  @override
  Future<String> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async =>
      'Password changed successfully.';

  @override
  Future<WalletBundle> wallets() async => _wallets;

  @override
  Future<List<LedgerTransaction>> recentTransactions({int limit = 3}) async =>
      [LedgerTransaction.fromJson(transactionJson())];

  @override
  Future<TransactionListData> transactions({String wallet = 'ALL'}) async {
    final allItems = [
      transactionJson(
        id: 'tx-shuttle',
        vendor: 'SHUTTLE',
        title: 'Shuttle fare',
        amount: '21.50',
        sign: '-',
      ),
      transactionJson(
        id: 'tx-deposit',
        type: 'DEPOSIT',
        vendor: 'MAIN',
        title: 'Card deposit',
        subtitle: 'Simulated top-up',
        amount: '500.00',
        sign: '+',
      ),
    ];
    final filtered = wallet == 'ALL'
        ? allItems
        : allItems.where((tx) => tx['vendor_code'] == wallet).toList();

    return TransactionListData.fromJson({
      'filter': wallet,
      'summary': {
        'deposited_this_month': '500.00',
        'deposited_this_month_formatted': '+₺500,00',
        'spent_this_month': '125.50',
        'spent_this_month_formatted': '-₺125,50',
      },
      'transactions': filtered,
    });
  }

  @override
  Future<LedgerResult> deposit({
    required int amount,
    required String idempotencyKey,
  }) async {
    deposits.add(amount);
    mainBalance += amount;
    return LedgerResult.fromJson({
      'message': 'Deposit completed.',
      'idempotent_replay': false,
      'data': {
        'transaction': transactionJson(
          type: 'DEPOSIT',
          amount: _api(amount),
          sign: '+',
          vendor: 'MAIN',
          title: 'Card deposit',
        ),
        'wallets': walletsJson(main: _api(mainBalance)),
      },
    });
  }

  @override
  Future<LedgerResult> allocate({
    required String vendorCode,
    required int amount,
    required String idempotencyKey,
  }) async {
    allocations.add((vendorCode, amount));
    mainBalance -= amount;
    return LedgerResult.fromJson({
      'message': 'Funds allocated to service wallet.',
      'idempotent_replay': false,
      'data': {
        'transaction': transactionJson(
          type: 'ALLOCATION',
          amount: _api(amount),
          sign: '',
          vendor: vendorCode,
          title: 'Allocated',
        ),
        'wallets': walletsJson(main: _api(mainBalance)),
      },
    });
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

Future<FakeRepository> pumpApp(
    WidgetTester tester, {
      Map<String, Object> prefs = const {},
    }) async {
  tester.view.physicalSize = const Size(1170, 2532);
  tester.view.devicePixelRatio = 3;
  addTearDown(tester.view.reset);

  SharedPreferences.setMockInitialValues(prefs);
  final sharedPrefs = await SharedPreferences.getInstance();
  final repository = FakeRepository();

  await tester.pumpWidget(
    ProviderScope(
      retry: (_, _) => null,
      overrides: [
        sharedPreferencesProvider.overrideWithValue(sharedPrefs),
        repositoryProvider.overrideWithValue(repository),
      ],
      child: const SusWalletApp(useGoogleFonts: false),
    ),
  );
  await tester.pumpAndSettle();
  return repository;
}

void main() {
  testWidgets('shows Login when no session is stored', (tester) async {
    await pumpApp(tester);
    expect(find.byType(LoginScreen), findsOneWidget);
    expect(find.text('Welcome back'), findsOneWidget);
  });

  testWidgets('validates university email before calling the API',
          (tester) async {
        await pumpApp(tester);

        await tester.enterText(find.byType(TextFormField).first, 'me@gmail.com');
        await tester.enterText(find.byType(TextFormField).last, 'password123');
        await tester.tap(find.text('Log In'));
        await tester.pumpAndSettle();

        expect(
          find.text('Only @sabanciuniv.edu accounts are supported.'),
          findsOneWidget,
        );
        expect(find.byType(LoginScreen), findsOneWidget);
      });

  testWidgets('shows the API error for wrong credentials', (tester) async {
    await pumpApp(tester);

    await tester.enterText(
        find.byType(TextFormField).first, 'student@sabanciuniv.edu');
    await tester.enterText(find.byType(TextFormField).last, 'wrong-pass');
    await tester.tap(find.text('Log In'));
    await tester.pumpAndSettle();

    expect(find.text('Invalid university email or password.'), findsOneWidget);
  });

  testWidgets('logs in, shows balances, and deposits into the Main Wallet',
          (tester) async {
        final repository = await pumpApp(tester);

        await tester.enterText(
            find.byType(TextFormField).first, 'student@sabanciuniv.edu');
        await tester.enterText(find.byType(TextFormField).last, 'password123');
        await tester.tap(find.text('Log In'));
        await tester.pumpAndSettle();

        // Home dashboard with the wireframe balances.
        expect(find.text('Student Name'), findsOneWidget);
        expect(find.text('₺1.250,00'), findsOneWidget);
        expect(find.text('₺85,50'), findsOneWidget);
        expect(find.text('Shuttle fare'), findsOneWidget);

        // Deposit pop-up with the default ₺250 quick amount.
        await tester.tap(find.text('Deposit').first);
        await tester.pumpAndSettle();
        expect(find.text('New Main Wallet balance'), findsOneWidget);
        expect(find.text('₺1.500,00'), findsOneWidget);

        await tester.tap(find.text('Deposit ₺250,00'));
        await tester.pumpAndSettle();

        expect(repository.deposits, [25000]);
        expect(find.text('₺1.500,00'), findsOneWidget);
        expect(find.text('New Main Wallet balance'), findsNothing);
      });

  testWidgets('restores a saved session and tops up from Services',
          (tester) async {
        final repository = await pumpApp(
          tester,
          prefs: {'suswallet.auth_token': 'saved-token'},
        );

        expect(find.byType(LoginScreen), findsNothing);
        await tester.tap(find.text('Services'));
        await tester.pumpAndSettle();
        expect(find.text('Locked to shuttle vendor'), findsOneWidget);

        // Shuttle "Top up" opens the pop-up with the default ₺50 amount.
        await tester.tap(find.text('Top up').first);
        await tester.pumpAndSettle();
        expect(find.text('Top up Shuttle'), findsNWidgets(2));
        expect(find.text('₺135,50'), findsOneWidget); // Shuttle after

        await tester.tap(find.text('Top up Shuttle').last);
        await tester.pumpAndSettle();

        expect(repository.allocations, [('SHUTTLE', 5000)]);
        expect(find.text('₺1.200,00'), findsOneWidget);
      });

  testWidgets('Transactions tab filters, shows summary, and removes cards',
          (tester) async {
        await pumpApp(tester, prefs: {'suswallet.auth_token': 'saved-token'});

        // "See all" on Home jumps to the Transactions tab.
        await tester.tap(find.text('See all'));
        await tester.pumpAndSettle();

        expect(find.text('Deposited this month'), findsOneWidget);
        expect(find.text('+₺500,00'), findsWidgets);
        expect(find.text('Card deposit'), findsOneWidget);

        // Filter by Shuttle hides Main deposit.
        await tester.tap(find.text('Shuttle').last);
        await tester.pumpAndSettle();
        expect(find.text('Card deposit'), findsNothing);
        expect(find.text('Shuttle fare'), findsWidgets);

        // Remove button removes the transaction Card from the list.
        await tester.tap(find.byTooltip('Remove transaction').first);
        await tester.pumpAndSettle();
        expect(find.text('No transactions found for this wallet.'), findsOneWidget);
      });

  testWidgets('Settings tab navigates to Profile, Edit profile, and Security',
          (tester) async {
        await pumpApp(tester, prefs: {'suswallet.auth_token': 'saved-token'});

        await tester.tap(find.text('Settings'));
        await tester.pumpAndSettle();
        expect(find.text('SUCard & Security'), findsOneWidget);

        // Open Profile -> Edit profile and verify validation + confirmation dialog.
        await tester.tap(find.text('Profile'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Edit profile'));
        await tester.pumpAndSettle();

        await tester.enterText(find.byType(TextFormField).first, 'A');
        await tester.ensureVisible(find.text('Save changes'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Save changes'));
        await tester.pumpAndSettle();

        await tester.ensureVisible(find.byType(TextFormField).first);
        await tester.pumpAndSettle();
        expect(
          find.text('Full name must be at least 2 characters.'),
          findsOneWidget,
        );

        await tester.enterText(find.byType(TextFormField).first, 'Usman Khan');
        await tester.ensureVisible(find.text('Save changes'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Save changes'));
        await tester.pumpAndSettle();
        expect(find.text('Save profile changes?'), findsOneWidget);

        await tester.tap(find.text('Confirm'));
        await tester.pumpAndSettle();
        expect(find.text('Usman Khan'), findsOneWidget);
      });
}