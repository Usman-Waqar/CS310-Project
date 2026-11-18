# SUSWallet Mobile (Flutter)

Flutter client for the SUSWallet Laravel API in `../suswallet-backend`.

## Status

| Screen (Step 2.1 wireframes) | Status | API |
| :--- | :--- | :--- |
| Login, Forgot password | Done | `POST /auth/login`, `POST /auth/forgot-password` |
| Home (header, balances, service tiles, recent transactions) | Done | `GET /auth/me`, `GET /wallets`, `GET /transactions?limit=3` |
| Services | Done | `GET /wallets` |
| Deposit pop-up, Top up pop-up | Done | `POST /transactions/deposit`, `POST /transactions/allocate` |
| Home → Simulation tools | To do | `POST /transactions/tap`, `POST /transactions/refund-last-tap` |
| Transactions | To do (empty tab) | `GET /transactions?wallet=…` |
| Settings, Profile, Edit profile, SUCard & Security, Log Out | To do (empty tab) | `GET /profile`, `PATCH /profile`, `PUT /auth/password`, `POST /auth/logout` |

The Transactions and Settings tabs are wired into the bottom navigation as
empty pages (`lib/features/shell/placeholder_tab.dart`); Home's "See all" opens
the Transactions tab. Replace the `PlaceholderTab` entries in
`lib/features/shell/main_shell.dart` with the real screens.

## Project structure

```text
lib/
├── main.dart, app.dart        # ProviderScope + session gate (Login vs main app)
├── core/
│   ├── api/                   # Dio client (Bearer token), base URL, ApiException
│   ├── storage/               # SharedPreferences token + cached profile
│   ├── theme/                 # Colours, Poppins theme, per-service styles
│   └── utils/                 # Integer-kuruş Money helpers (₺1.250,00), time labels
├── models/                    # UserProfile, Wallet/Vendor, LedgerTransaction
├── data/                      # SusWalletRepository: one method per endpoint
├── state/                     # Riverpod: session, wallets, recent list, ledger actions, tab
├── widgets/                   # Shared cards, headers, form fields
└── features/                  # auth, shell (bottom nav), home, services,
                               # transfers (Deposit / Top up pop-ups)
```

Money is handled as integer kuruş on the client so previews never drift, and
every deposit / top-up sends a UUID `idempotency_key`.

## Running

1. Start the backend (from `suswallet-backend/`):

   ```powershell
   php artisan migrate:fresh --seed --no-interaction
   php artisan serve
   ```

   Demo account: `student@sabanciuniv.edu` / `password123`.

2. Run the app (from `suswallet-mobile/`):

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

`test/app_flow_test.dart` drives Login → Home → Deposit, Services → Top up and
the bottom navigation against an in-memory fake repository, so it needs no
running backend.
