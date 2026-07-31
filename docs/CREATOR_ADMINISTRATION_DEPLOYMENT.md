# Creator administration deployment

The creator administration upgrade is database-backed. Uploading only the
Filament resource files is not sufficient.

## Required production commands

Run these from the Laravel application root after uploading the complete file
set:

```bash
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\CreatorPlatformSeeder --force
php artisan optimize:clear
php artisan filament:clear-cached-components
php artisan optimize
php artisan queue:restart
```

Before running the new administration migration, confirm these foundation
migration files are present on the server:

- `2026_07_25_000200_rebuild_creator_platform.php`
- `2026_07_25_000210_complete_creator_platform.php`
- `2026_07_26_000100_complete_creator_administration.php`

The seeder is idempotent. It preserves existing customized email templates,
financial settings, payout options and creator permissions. It fills missing
creator-platform defaults and capabilities.

## Operational defaults

- Creator share: 70%
- Platform share: 30%
- Subscription creator pool: 40%
- Currency: UGX
- Manual payout: enabled
- Automatic payout: disabled
- Manual approval: required
- ioTec: catalogued as automatic, but unavailable until client ID, client
  secret and wallet ID are configured

## Existing environment configuration

No new environment variable was introduced. Automated ioTec payout readiness
uses the existing Payment Gateway configuration or these existing variables:

```dotenv
IOTEC_CLIENT_ID=
IOTEC_CLIENT_SECRET=
IOTEC_WALLET_ID=
IOTEC_GRANT_TYPE=client_credentials
IOTEC_ID_BASE_URL=https://id.iotec.io
IOTEC_PAY_BASE_URL=https://pay.iotec.io
IOTEC_WEBHOOK_TOKEN=
```

Lifecycle email delivery also requires the existing mail and queue settings to
be valid and a queue worker to be running.

## Post-deployment checks

1. Open **Creator Economy Settings** and verify that creator and platform
   shares total 100%.
2. Open **Payout Methods**, run **Check configuration** on ioTec, and enable it
   only after it reports configured.
3. Confirm **Creator Permissions** has an **Assign creator permissions** action.
4. Open a VJ claim and a creator application to confirm that individual review
   pages load.
5. Confirm `php artisan queue:restart` completed and process one test lifecycle
   email before enabling automatic payout.
