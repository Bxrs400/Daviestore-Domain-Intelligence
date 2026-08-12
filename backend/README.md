# Domain Intel Laravel 11 backend

This directory is an application-layer scaffold to copy into a fresh Laravel 11 project. It contains real discovery, crawling, DNS/HTTP validation, classification, persistence, queue orchestration, and API resources.

## API contract

The exact frontend contract is documented in [`API_CONTRACT.md`](./API_CONTRACT.md) and mirrored by `lib/domain-api.ts`. JSON success responses use `{\"success\":true,\"data\":...}`; validation and transition failures use the documented `{\"success\":false,\"error\":{...}}` envelope.

### Routes

- `POST /api/v1/collection-jobs` accepts `{name, source, options}` and returns HTTP 202 with a `CollectionJobResponse`.
- `GET /api/v1/collection-jobs/{id}` returns a `CollectionJobResponse`.
- `GET /api/v1/collection-jobs/{id}/logs?page=1&per_page=25` returns typed pagination metadata and `CollectionLogResponse` items.
- `POST /api/v1/collection-jobs/{id}/pause` and `/resume` return the updated job or HTTP 409 `INVALID_STATUS_TRANSITION`.
- `GET /api/v1/domains?search=linear&industry=saas&page=1&per_page=50` returns typed domain pagination.
- `GET /api/v1/domains/export` streams `domain-export.csv` with stable CSV headers.

The frontend route strings and response field names are intentionally unchanged.

## Run

1. Create a Laravel 11 app and copy this directory's `app/`, `routes/api.php`, and migrations into it.
2. Install the DOM crawler dependency: `composer require symfony/dom-crawler symfony/css-selector`.
3. Configure MySQL in `.env`, set `QUEUE_CONNECTION=redis`, and run `php artisan migrate`.
4. Start the API with `php artisan serve`.
5. Start workers with `php artisan queue:work redis --queue=default --tries=3 --backoff=5`.

## Connect Next.js

Set `NEXT_PUBLIC_LARAVEL_API_URL=http://127.0.0.1:8000` and optionally `NEXT_PUBLIC_LARAVEL_ACTIVE_JOB_ID=1` in the Next.js environment. The dashboard polls the configured job, logs, and domain endpoints every 10 seconds; if the URL is absent or unreachable, it keeps the local preview data.
