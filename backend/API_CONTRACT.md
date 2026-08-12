# Laravel API contract

Base URL: `${NEXT_PUBLIC_LARAVEL_API_URL}/api/v1`

The frontend contract in `lib/domain-api.ts` is authoritative. Every JSON success response uses `{ "success": true, "data": ... }`. Every JSON failure uses `{ "success": false, "error": { "code": string, "message": string, "fields"?: object } }`.

| Method | Route | Request | Response |
| --- | --- | --- | --- |
| POST | `/collection-jobs` | `{name:string, source:string, options:object}` | `202`, `data` is a `CollectionJobResponse` |
| GET | `/collection-jobs/{id}` | none | `data` is a `CollectionJobResponse` |
| GET | `/collection-jobs/{id}/logs?per_page=25&page=1` | none | `data` is `Pagination<CollectionLogResponse>` |
| POST | `/collection-jobs/{id}/pause` | none | `data` is a `CollectionJobResponse` |
| POST | `/collection-jobs/{id}/resume` | none | `data` is a `CollectionJobResponse` |
| GET | `/domains?per_page=50&page=1&industry=x&search=y` | none | `data` is `Pagination<DomainResponse>` |
| GET | `/domains/export` | optional filters | CSV download, `Content-Type: text/csv; charset=UTF-8` |

## Pagination

Paginated responses contain `data`, `current_page`, `per_page`, `last_page`, `from`, `to`, and `total`. `from` and `to` are `null` for empty results. `per_page` is clamped to 1–100.

## Statuses

Collection jobs: `queued`, `running`, `paused`, `completed`, `failed`.

Domains: `pending`, `verified`, `rejected`, `failed`.

## Error codes

- `VALIDATION_ERROR` — HTTP 422 with `fields` keyed by request field.
- `INVALID_STATUS_TRANSITION` — HTTP 409 when pause/resume is not valid for the current job status.
- Laravel route model binding returns the framework 404 response for unknown IDs; production API exception rendering should normalize this to `MODEL_NOT_FOUND` using the same error envelope.

No frontend route strings or field names are changed by this contract.
