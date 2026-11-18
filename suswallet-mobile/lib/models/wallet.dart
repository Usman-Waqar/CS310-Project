import '../core/utils/money.dart';

/// Mirrors the backend `VendorResource`.
class Vendor {
  const Vendor({
    required this.id,
    required this.code,
    required this.name,
    required this.category,
    required this.defaultTapPrice,
    required this.iconName,
  });

  final String id;
  final String code;
  final String name;
  final String category;

  /// Default tap price in kuruş (e.g. 2150 for ₺21,50).
  final int defaultTapPrice;
  final String iconName;

  factory Vendor.fromJson(Map<String, dynamic> json) => Vendor(
        id: (json['id'] ?? '').toString(),
        code: (json['code'] ?? '').toString(),
        name: (json['name'] ?? '').toString(),
        category: (json['category'] ?? '').toString(),
        defaultTapPrice: Money.parseApi(json['default_tap_price']),
        iconName: (json['icon_name'] ?? '').toString(),
      );
}

/// Mirrors the backend `WalletResource`.
class Wallet {
  const Wallet({
    required this.id,
    required this.isMain,
    required this.balance,
    required this.currency,
    this.vendor,
  });

  final String id;
  final bool isMain;

  /// Balance in kuruş.
  final int balance;
  final String currency;
  final Vendor? vendor;

  String get code => isMain ? 'MAIN' : (vendor?.code ?? 'SERVICE');
  String get name => isMain ? 'Main Wallet' : (vendor?.name ?? 'Service');

  factory Wallet.fromJson(Map<String, dynamic> json) => Wallet(
        id: (json['id'] ?? '').toString(),
        isMain: json['is_main'] == true,
        balance: Money.parseApi(json['balance']),
        currency: (json['currency'] ?? 'TRY').toString(),
        vendor: json['vendor'] is Map
            ? Vendor.fromJson(Map<String, dynamic>.from(json['vendor'] as Map))
            : null,
      );
}

/// The `{ main_wallet, service_wallets }` payload from `GET /wallets`
/// (also embedded in every ledger mutation response).
class WalletBundle {
  const WalletBundle({required this.main, required this.services});

  const WalletBundle.empty()
      : main = null,
        services = const [];

  final Wallet? main;
  final List<Wallet> services;

  int get mainBalance => main?.balance ?? 0;

  Wallet? serviceFor(String code) {
    for (final wallet in services) {
      if (wallet.vendor?.code == code) return wallet;
    }
    return null;
  }

  factory WalletBundle.fromJson(Map<String, dynamic> json) {
    final main = json['main_wallet'];
    final services = json['service_wallets'];
    return WalletBundle(
      main: main is Map
          ? Wallet.fromJson(Map<String, dynamic>.from(main))
          : null,
      services: services is List
          ? services
              .whereType<Map>()
              .map((item) => Wallet.fromJson(Map<String, dynamic>.from(item)))
              .toList()
          : const [],
    );
  }
}
