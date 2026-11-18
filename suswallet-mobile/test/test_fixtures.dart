// Sample payloads copied from the backend API contract
// (BACKEND_DEVELOPMENT_GUIDE.md §8) and the demo seeder.

Map<String, dynamic> userJson({String fullName = 'Student Name'}) => {
      'id': '01a1-demo',
      'student_id': '00031234',
      'su_username': 'student',
      'email': 'student@sabanciuniv.edu',
      'full_name': fullName,
      'initials': 'SN',
      'phone': '+905321234512',
      'masked_phone': '+90 5** *** ** 12',
      'avatar_path': null,
      'avatar_url': null,
      'is_active': true,
      'sucard_status': 'Active',
    };

Map<String, dynamic> vendorJson(String code, String name, String price) => {
      'id': 'vendor-$code',
      'code': code,
      'name': name,
      'category': 'X',
      'default_tap_price': price,
      'default_tap_price_formatted': '',
      'icon_name': 'store',
    };

Map<String, dynamic> walletsJson({String main = '1250.00'}) => {
      'main_wallet': {
        'id': 'wallet-main',
        'is_main': true,
        'balance': main,
        'balance_formatted': '',
        'currency': 'TRY',
        'vendor': null,
      },
      'service_wallets': [
        {
          'id': 'wallet-shuttle',
          'is_main': false,
          'balance': '85.50',
          'currency': 'TRY',
          'vendor': vendorJson('SHUTTLE', 'Shuttle', '21.50'),
        },
        {
          'id': 'wallet-cafeteria',
          'is_main': false,
          'balance': '240.00',
          'currency': 'TRY',
          'vendor': vendorJson('CAFETERIA', 'Cafeteria', '96.00'),
        },
        {
          'id': 'wallet-printing',
          'is_main': false,
          'balance': '40.00',
          'currency': 'TRY',
          'vendor': vendorJson('PRINTING', 'Printing', '8.00'),
        },
      ],
    };

Map<String, dynamic> transactionJson({
  String id = 'tx-1',
  String type = 'CARD_TAP',
  String amount = '21.50',
  String sign = '-',
  String vendor = 'SHUTTLE',
  String title = 'Shuttle fare',
  String subtitle = 'Card tap · Shuttle',
  bool refunded = false,
  String? createdAt,
}) =>
    {
      'id': id,
      'type': type,
      'status': 'COMPLETED',
      'amount': amount,
      'amount_formatted': '$sign₺${amount.replaceAll('.', ',')}',
      'sign': sign,
      'vendor_code': vendor,
      'title': title,
      'subtitle': subtitle,
      'is_refunded': refunded,
      'created_at': createdAt ?? DateTime.now().toIso8601String(),
    };
