# Deployment workflow — NaraBox TV Portal (with API docs)

This is the **complete workflow** for updating the portal (including the API documentation) on Hostinger or any production server. Scribe is a **production dependency**, so `composer install --no-dev --optimize-autoloader` will install it and the docs UI will work after you run the generate step.

---

## 1. Composer: Scribe is production-ready

- **knuckleswtf/scribe** is in **`require`** (not `require-dev`).
- Running **`composer install --no-dev --optimize-autoloader`** will install Scribe.
- The docs are **generated at deploy time** (see below); no need to commit generated Blade/OpenAPI into the repo unless you prefer that.

---

## 2. Local workflow (before you deploy)

Use this when you’ve changed code or docs and want to prepare for deploy.

1. **Make your changes** (controllers, config, markdown in `docs/`, etc.).
2. **Regenerate API docs** (optional locally; production will do it too):
   ```bash
   cd /path/to/naraboxt-lara
   php artisan scribe:generate
   ```
3. **Copy OpenAPI so the docs route works** (optional locally):
   ```bash
   mkdir -p storage/app/scribe
   cp storage/app/private/scribe/openapi.yaml storage/app/scribe/
   cp storage/app/private/scribe/collection.json storage/app/scribe/
   cp storage/app/private/scribe/openapi.yaml docs/openapi.yaml
   ```
4. **Commit and push** (or prepare your upload):
   - Commit: `config/scribe.php`, `app/Http/Controllers/Api/*`, `docs/*.md`, `docs/README.md`, `composer.json`, `composer.lock`, and root `API_DOCUMENTATION_IMPLEMENTATION_SUMMARY.md`.
   - You can **omit** committing `storage/app/scribe/` and `resources/views/scribe/` and `public/vendor/scribe/` — production will regenerate them in the deploy step.

---

## 3. Production deploy (Hostinger or any server)

Run these on the server (or in your deploy script) **after** uploading files or pulling from Git.

### Step 1: Go to project root

```bash
cd /path/to/naraboxt-lara
# e.g. on Hostinger: cd domains/portal.naraboxtv.com/public_html
# or wherever your Laravel app root is
```

### Step 2: Install dependencies (no dev, optimized)

```bash
composer install --no-dev --optimize-autoloader
```

This installs Scribe (it’s in `require`), so the docs package and routes will be available.

### Step 3: Generate API docs

```bash
php artisan scribe:generate
```

This writes:

- Blade views to `resources/views/scribe/`
- Assets to `public/vendor/scribe/`
- OpenAPI and Postman to `storage/app/private/scribe/`

### Step 4: Copy OpenAPI and Postman so routes work

Scribe serves the OpenAPI and Postman files from `storage/app/scribe/`. Copy from the private folder:

```bash
mkdir -p storage/app/scribe
cp storage/app/private/scribe/openapi.yaml storage/app/scribe/
cp storage/app/private/scribe/collection.json storage/app/scribe/
```

Optional: keep a copy in `docs/` for versioning or static access:

```bash
cp storage/app/private/scribe/openapi.yaml docs/openapi.yaml
```

### Step 5: Laravel optimizations (recommended)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 6: Migrations (if needed)

```bash
php artisan migrate --force
```

### Step 7: Restart queue / PHP (if applicable)

- If you use queue workers: restart them.
- If you use PHP-FPM: reload or restart the service (or your host’s “Restart PHP” option).

---

## 4. One-line deploy script (optional)

You can put the production steps in a script, e.g. `deploy.sh` in the project root:

```bash
#!/bin/bash
set -e
cd "$(dirname "$0")"

echo "Installing dependencies..."
composer install --no-dev --optimize-autoloader

echo "Generating API docs..."
php artisan scribe:generate

echo "Copying OpenAPI and Postman..."
mkdir -p storage/app/scribe
cp storage/app/private/scribe/openapi.yaml storage/app/scribe/
cp storage/app/private/scribe/collection.json storage/app/scribe/
cp storage/app/private/scribe/openapi.yaml docs/openapi.yaml

echo "Caching..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Migrations (if any)..."
php artisan migrate --force

echo "Deploy done. Docs: /docs/api/v1"
```

Make it executable: `chmod +x deploy.sh`. On Hostinger you can run it via SSH or a “Run script” feature if available.

---

## 5. Verify docs on production

- **HTML docs:** `https://portal.naraboxtv.com/docs/api/v1`
- **OpenAPI:** `https://portal.naraboxtv.com/docs/api/v1.openapi`
- **Postman:** `https://portal.naraboxtv.com/docs/api/v1.postman`

---

## 6. Summary table

| Step | Command / action |
|------|-------------------|
| 1. Dependencies | `composer install --no-dev --optimize-autoloader` |
| 2. Generate docs | `php artisan scribe:generate` |
| 3. Copy for routes | `mkdir -p storage/app/scribe` then copy `openapi.yaml` and `collection.json` from `storage/app/private/scribe/` to `storage/app/scribe/` |
| 4. Cache | `php artisan config:cache && php artisan route:cache && php artisan view:cache` |
| 5. Migrate | `php artisan migrate --force` (if needed) |

Scribe is **included** when you run `composer install --no-dev --optimize-autoloader` because it lives in **`require`**. The workflow above is the complete way to update the portal with the new API docs on Hostinger (or any host).
