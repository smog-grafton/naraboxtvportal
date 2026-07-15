# NaraBox TV Portal — API & backend docs

This folder contains **human- and AI-friendly** documentation for the NaraBox TV Portal backend (naraboxt-lara). Use it to build a **mobile app**, integrate with the API, or let AI tools understand the system.

---

## Entry points

| Doc | Use when |
|-----|----------|
| **[API_OVERVIEW.md](API_OVERVIEW.md)** | You need a high-level map of the API (domains, auth, workflows). |
| **[API_REFERENCE_WITH_CODE_SAMPLES.md](API_REFERENCE_WITH_CODE_SAMPLES.md)** | You need **cURL and JavaScript** examples for every endpoint (mobile, web, AI). |
| **[CONTROLLERS_INDEX.md](CONTROLLERS_INDEX.md)** | You need to know **which controller** backs which route and what is public vs internal. |
| **[DATABASE_SCHEMA.md](DATABASE_SCHEMA.md)** | You need **database** tables and columns (naraboxt-lara). |
| **[PAYMENT_GATEWAYS.md](PAYMENT_GATEWAYS.md)** | You need **payment** DB structure, gateway types, and **code samples** (Flutterwave, ioTec, PawaPay, manual). |
| **[AUTHENTICATION.md](AUTHENTICATION.md)** | You need **auth** flows (register, login, OAuth, token usage). |
| **[DOMAIN_MODEL.md](DOMAIN_MODEL.md)** | You need **entities** and relationships (movies, TV shows, VJs, access, payments). |
| **[PLAYBACK_FLOW.md](PLAYBACK_FLOW.md)** | You need **playback** (access check → player → watch history, downloads). |
| **[FRONTEND_INTEGRATION_GUIDE.md](FRONTEND_INTEGRATION_GUIDE.md)** | You need **page-by-page** flows (homepage, detail, watch, subscription, profile). |
| **[ERRORS_AND_STATUS_CODES.md](ERRORS_AND_STATUS_CODES.md)** | You need **error** shapes and status codes. |
| **[MEDIA_PIPELINE.md](MEDIA_PIPELINE.md)** | You need **backend context** (ingest, CDN, worker). |
| **[UPLOAD_AND_IMPORT_FLOW.md](UPLOAD_AND_IMPORT_FLOW.md)** | You need **operator** upload/import (not consumer API). |
| **[REGENERATE.md](REGENERATE.md)** | You need to **regenerate** Scribe docs and OpenAPI. |
| **[DEPLOYMENT_WORKFLOW.md](DEPLOYMENT_WORKFLOW.md)** | **Full deploy workflow** (Hostinger): `composer install --no-dev`, generate docs, copy OpenAPI, cache. Scribe is in `require` so it works with `--no-dev`. |

---

## Generated API reference (Scribe)

- **HTML (interactive):** `/docs/api/v1` (e.g. `https://portal.naraboxtv.com/docs/api/v1`)
- **OpenAPI YAML:** `/docs/api/v1.openapi` or this folder: **[openapi.yaml](openapi.yaml)**
- **Postman:** `/docs/api/v1.postman`

---

## Base URL and versioning

- **Base path:** `https://portal.naraboxtv.com/api/v1` (or your backend URL).
- **Auth:** `Authorization: Bearer <token>` (token from login/register).
- **Headers:** `Accept: application/json`, `Content-Type: application/json` for JSON bodies.

---

## For mobile developers

1. Read **API_OVERVIEW.md** and **AUTHENTICATION.md**.
2. Use **API_REFERENCE_WITH_CODE_SAMPLES.md** for every endpoint (adapt fetch to your stack).
3. Use **PAYMENT_GATEWAYS.md** for payments (gateways list, initiate, upload proof, verify, Flutterwave/ioTec/PawaPay).
4. Use **CONTROLLERS_INDEX.md** to see which controller handles which route.
5. Use **openapi.yaml** for codegen or API clients if needed.

---

## For AI / codegen

- **OpenAPI:** `docs/openapi.yaml` or GET `/docs/api/v1.openapi`.
- **Structured workflows:** **FRONTEND_INTEGRATION_GUIDE.md**, **PLAYBACK_FLOW.md**, **DOMAIN_MODEL.md**.
- **Data model:** **DATABASE_SCHEMA.md**, **PAYMENT_GATEWAYS.md** (payment_gateways, payment_transactions, flows).
