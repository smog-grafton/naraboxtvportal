# NaraBox active-payment-abuse security runbook

This patch treats Laravel as the authoritative enforcement layer. Web and mobile clients only add a best-effort installation identifier and display safe `403`/`429` responses; a missing or forged device identifier never bypasses user, provider, IP, payer, rule, entitlement, or account-state controls.

## Production rollout order

1. Back up the database and preserve payment/provider evidence. Do not delete users, payment attempts, transactions, security events, or provider callbacks during the investigation.
2. Deploy the Laravel code before the clients.
3. Configure a shared cache store for every API instance and queue worker. Redis is preferred; the database cache is supported when all instances use the same database. Run `php artisan config:cache` after setting production values.
4. Set `SECURITY_TRUSTED_PROXIES` to the exact load-balancer/Cloudflare egress IPs or CIDRs that directly connect to Laravel. Never use `*`. `CF-Connecting-IP` is ignored unless `REMOTE_ADDR` matches this list.
5. Configure callback authentication before enabling payment initiation:
   - `FLW_WEBHOOK_SECRET_HASH` for Flutterwave.
   - `PAWAPAY_WEBHOOK_TOKEN` for PawaPay, or enable the existing verified callback-signature integration. If signature verification is enabled without a usable token, callbacks fail closed.
   - Keep the existing IoTeC webhook token configured. Successful IoTeC callbacks are independently confirmed with the provider status API.
   Provider callbacks deliberately bypass the NaraBox public-client API key and customer session middleware; their provider-specific secret/signature is mandatory instead.
6. Run only the incident migrations if other unrelated migrations are pending:

   ```bash
   php artisan migrate --path=database/migrations/2026_08_23_000100_create_security_incident_tables.php --force
   php artisan migrate --path=database/migrations/2026_08_23_000200_add_security_fields_to_users_and_payments.php --force
   php artisan migrate --path=database/migrations/2026_08_23_000300_add_access_grant_idempotency_to_payment_transactions.php --force
   php artisan db:seed --class=SecuritySettingsSeeder --force
   php artisan db:seed --class=CurrentIncidentSecurityRulesSeeder --force
   ```

7. Confirm the six precise incident rules exist: `Diego Alvarez` and `Ssemakula John`, each for registration, login, and payment. These are full-name token-set matches; neither shared name token is blocked alone. Exact emails/provider identities should be added only after they are verified against production evidence.
8. Open Filament and verify **Security Dashboard**, **Emergency Security Controls**, **Security Rules**, **Security Events**, and **Protected Payers**. Leave registrations and payments enabled for normal traffic unless incident response requires a shutdown.
9. Deploy Next.js, then mobile version `1.4.16` (Android version code `19`, iOS build `10`). Older clients remain compatible because `X-NBX-Device-ID` is optional.
10. Restart API/queue processes and monitor `security_events`, `payment_attempts`, callback failures, `429` rates, and provider dashboards.

## Emergency controls

Filament runtime controls can independently disable registrations, new Google registrations, all payments, IoTeC, Mobile Money, cards, automatic restrictions, or enumeration detection. Lockdown disables new registrations and payment initiation while authenticated non-payment access continues.

Use the narrowest control that contains the incident. Protect a reported payer number before investigating correlated accounts. Restrict payment initiation when abuse is suspected; suspend or ban only with documented evidence. Ban creates persistent hashed identity records for email, phone, and linked provider subjects, so deleting and recreating the user row does not evade enforcement.

The UI and service layer prevent an administrator from suspending/banning their own account and prevent action against the final active administrator.

## Verification checklist

- A protected payer receives a generic safe rejection before a transaction/provider request is created.
- A restricted, suspended, or banned account cannot initiate payment; suspended/banned sessions and Sanctum tokens are revoked.
- A banned email/provider subject remains blocked after the original user row is removed.
- Reordered/case-varied configured full-name tokens match; an individual surname does not.
- Repeated and sequential payer-number attempts are rate-limited/restricted across user, provider identity, IP, optional device, and payer dimensions.
- Duplicate callbacks cannot grant access or allocate revenue twice; amount, currency, reference, gateway, signature/token, and provider status are validated before fulfillment.
- Active subscription/purchase/rental entitlement is rejected before another payment request.
- A request without `X-NBX-Device-ID` still works when no authoritative control rejects it.

Run:

```bash
php artisan test tests/Unit/IdentityNormalizerTest.php tests/Feature/SecurityIncidentProtectionTest.php tests/Feature/SecurityControlMatrixTest.php
php artisan test tests/Feature/IoTeCCardCheckoutControllerTest.php tests/Feature/IoTeCServiceCardTest.php
```

## Rollback and recovery

Prefer Filament runtime switches over rolling back database structures during an active incident. The migrations are additive and preserve evidence. If application rollback is unavoidable, first disable new registrations and payment initiation, keep the incident tables intact, deploy the prior application, and investigate compatibility before any `migrate:rollback`. Never roll back or truncate evidence tables merely to re-enable traffic.

The backend dependency tree currently requires PHP 8.4 even though `composer.json` states PHP `^8.2`; this workstation's XAMPP PHP 8.2.4 cannot load the installed vendor tree. Production/staging must use PHP 8.4 for this exact lockfile or deliberately resolve and retest a PHP-8.2-compatible dependency lock before deployment.
