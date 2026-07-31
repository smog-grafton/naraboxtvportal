# Creator Platform Production Runbook

This runbook covers the Portal creator workflow, NBX resumable uploads, private
identity evidence, creator earnings and payouts. Run the steps in order and
take a database backup before migrating.

## 1. Pre-deployment checks

1. Back up the Portal database and confirm that the backup can be restored.
2. Deploy Portal and NBX from the tested revisions.
3. Keep `CREATOR_EVIDENCE_DISK=local` on a private, persistent Portal volume.
   Never expose `storage/app/creator-evidence` through the web server.
4. Configure long, random values for `IOTEC_WEBHOOK_TOKEN`,
   `PAWAPAY_REFUND_WEBHOOK_TOKEN`, `NBX_ENGINE_API_KEY` and
   `NBX_ENGINE_WEBHOOK_SECRET`. Do not reuse API keys between integrations.
5. Configure NBX CORS with the exact public frontend origins. Do not use `*`.
6. Ensure the reverse proxy permits an NBX upload chunk plus overhead. The
   default chunk is 8 MiB, so a 16 MiB request limit is a safe minimum. Large
   videos do not pass through PHP as one request.

## 2. Portal deployment

From the Portal release directory:

```bash
php artisan down
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan route:list --path=api/v1/creator
php artisan route:cache
php artisan up
```

Migration `2026_07_25_000210_complete_creator_platform` is intentionally
non-destructive on rollback. It adds creator metadata, ownership history,
profile-change review and support tables, and safely links payout methods where
no orphaned row exists.

If route caching reports duplicate `seo.movies`, the deployment contains an
older route file. Deploy the current `routes/web.php`, run
`php artisan optimize:clear`, and retry `php artisan route:cache`.

## 3. NBX deployment

Set the creator upload origins and limits, then clear cached configuration:

```bash
php artisan optimize:clear
php artisan optimize
php artisan route:cache
php artisan test
```

Keep the scheduler running so expired upload sessions and abandoned chunks are
removed. Monitor the configured upload-session volume as well as the media
working volume; resumable chunks exist temporarily before assembly.

## 4. Queue and scheduler

Run the Portal scheduler every minute:

```cron
* * * * * cd /path/to/portal && php artisan schedule:run >> /dev/null 2>&1
```

The schedule releases held earnings, reconciles withdrawals, deletes expired
verification evidence, checks NBX readiness and runs the configured queues. A
Supervisor-managed worker is preferred where available.

## 5. Existing payout records

First inspect, then encrypt legacy plaintext payout details:

```bash
php artisan creator:protect-payout-details --dry-run
php artisan creator:protect-payout-details
```

Do not run the second command until the dry-run count and the database backup
have been reviewed. Creator-facing APIs return only masked payout details.

## 6. Finance verification

Before enabling creator withdrawals:

1. Confirm one active `financial_settings` row with creator/platform shares,
   hold period, withdrawal limits and allowed payout methods.
2. Confirm only approved monetization settings generate creator liabilities.
3. Send signed test callbacks for payment success and refund. Replaying the same
   callback must not add a second ledger entry.
4. Run `php artisan creator:reconcile-withdrawals --limit=100` and verify pending
   payouts against the provider.
5. Require administrator evidence before marking a manual payout paid.
6. Never edit or delete creator ledger entries to correct a balance. Post an
   auditable reversal.

## 7. Smoke test

Use separate creator and administrator accounts:

1. Save a creator application and upload a private verification video.
2. Verify that another creator receives `403` for the evidence download.
3. Approve identity and explicitly enable only the intended permissions.
4. Create a movie draft with an ownership declaration.
5. Upload a video, pause it, reload the page, resume it and confirm processing.
6. Confirm the creator cannot activate or publish the title directly.
7. Approve the title in Filament and verify an HTTP-healthy HLS source becomes
   primary while playable MP4 remains a fallback.
8. Complete a test sale, verify pending/available wallet movement, request a
   payout, and replay every callback to prove idempotency.

## 8. Monitoring and incident handling

- Alert on growing NBX `failed`, `waiting_for_capacity` and stale upload-session
  counts.
- Alert on negative available creator balances; these can represent legitimate
  refund debt, but require review before another payout.
- Treat repeated webhook authentication failures as a key-rotation event.
- Suspend publishing/withdrawal permissions independently; do not delete the
  creator, evidence audit trail, content history, ledger or settlements.
- For an interrupted deploy, leave the site in maintenance mode until migrations
  and route caching both succeed.
