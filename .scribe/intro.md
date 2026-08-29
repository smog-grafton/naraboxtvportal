# Introduction

REST API for the NaraBox TV streaming portal. Powers the Next.js frontend with content (movies, TV shows, VJs), authentication, playback, subscriptions, rentals, and payments.

<aside>
    <strong>Base URL</strong>: <code>http://127.0.0.1:8000</code>
</aside>

    This documentation describes the **NaraBox TV Portal API v1**. Use it to build or integrate with the portal frontend (e.g. Next.js), understand workflows (homepage, watch page, dashboard, payments), and consume playback and access data.

    **Base path:** All documented endpoints are under `/api/v1/`.

    **Authentication:** Most listing and playback endpoints are public. Protected endpoints (dashboard, payments, watch history, profile) require a Bearer token from `POST /api/v1/auth/login` or `POST /api/v1/auth/register`.

    <aside>As you scroll, you'll see code examples for working with the API. You can switch the language with the tabs. Use "Try It Out" to call endpoints from the docs (CORS permitting).</aside>

    **Workflow and domain docs** (in the repo): See the `docs/` folder for API_OVERVIEW.md, AUTHENTICATION.md, DOMAIN_MODEL.md, PLAYBACK_FLOW.md, FRONTEND_INTEGRATION_GUIDE.md, and related guides for AI designers and frontend developers.

