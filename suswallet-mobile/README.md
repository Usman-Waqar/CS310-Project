# SUSWallet Mobile (Flutter)

Flutter client for the SUSWallet Laravel API in `../suswallet-backend`.

## Requirements

- **Flutter 3.47.6** (stable channel, Dart 3.13.5). Check with `flutter --version`.
- Android SDK with **NDK 28.2.13676358**
  (Android Studio → Settings → Languages & Frameworks → Android SDK → SDK Tools → NDK (Side by side)).
- PHP 8.4+ and Composer, to run the backend locally.

## Screens

| Screen | Where | API |
| :--- | :--- | :--- |
| Login, Forgot password | `features/auth` | `POST /auth/login`, `POST /auth/forgot-password` |
| Home (balances, service tiles, recent transactions) | `features/home` | `GET /auth/me`, `GET /wallets`, `GET /transactions?limit=3` |
| Home → Simulation tools (card tap, refund last tap) | `features/home` | `POST /transactions/tap`, `POST /transactions/refund-last-tap` |
| Deposit pop-up, Top up pop-up | `features/transfers` | `POST /transactions/deposit`, `POST /transactions/allocate` |
| Services | `features/services` | `GET /wallets` |
| Transactions (wallet filters, monthly summary, removable cards) | `features/transactions` | `GET /transactions?wallet=…` |
| Settings, Profile, Edit profile, SUCard & Security, Change password, Terms, About, Log out | `features/settings` | `PATCH /profile`, `PUT /auth/password`, `POST /auth/logout` |

## Project structure

```text
lib/
├── main.dart, app.dart        # ProviderScope + session gate (Login vs main app)
├── core/
│   ├── api/                   # Dio client (Bearer token), base URL, ApiException
│   ├── storage/               # SharedPreferences token + cached profile
│   ├── theme/                 # Colours, Poppins theme, per-service styles
│   └── utils/                 # Integer-kuruş Money helpers (₺1.250,00), time labels
├── models/                    # UserProfile, Wallet/Vendor, LedgerTransaction, summaries
├── data/                      # SusWalletRepository: one method per endpoint
├── state/                     # Riverpod: session, wallets, transactions, ledger actions, tab
├── widgets/                   # Shared cards, headers, form fields
└── features/                  # auth, shell (bottom nav), home, services, transfers,
                               # transactions, settings
assets/images/                 # Local images (SUCard badge)
```

Money is handled as integer kuruş on the client so amounts never drift, and
every deposit, top-up and card tap sends a UUID `idempotency_key`.

## Running

### 1. Backend (from `suswallet-backend/`)

```powershell
composer install
copy .env.example .env
php artisan key:generate
```

For a quick local database, open `.env`, set `DB_CONNECTION=sqlite` and delete
the `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` lines. Then:

```powershell
New-Item database/database.sqlite
php artisan migrate:fresh --seed --no-interaction
php artisan serve
```

Demo account: `student@sabanciuniv.edu` / `password123`.

### 2. App (from `suswallet-mobile/`)

Start an Android emulator, then:

```powershell
flutter pub get
flutter run
```

The API base URL is picked automatically:

- Android emulator: `http://10.0.2.2:8000/api/v1`
- iOS simulator / web / desktop: `http://127.0.0.1:8000/api/v1`
- Physical phone: run `php artisan serve --host=0.0.0.0 --port=8000`, then

  ```powershell
  flutter run --dart-define=API_BASE_URL=http://<YOUR_PC_LAN_IP>:8000/api/v1
  ```

Plain HTTP to the dev server is allowed in Android debug/profile builds only
(`android/app/src/debug/AndroidManifest.xml`).

## Tests

```powershell
flutter analyze
flutter test
```

`test/app_flow_test.dart` drives Login → Home → Deposit, Services → Top up,
Simulation tools, Transactions filters and remove, and Settings → Edit profile
against an in-memory fake repository, so it needs no running backend.