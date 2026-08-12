# Discovery providers

The discovery stage is provider-driven and additive to the existing Laravel 13.24 queue pipeline.

## Providers

- `local_seed`: uses validated `options.seed_urls`; works without external credentials.
- `google`: Google Custom Search JSON API; returns no results when key/CX are absent or the request fails.
- `wikidata`: public Wikidata entity search; only entities with a resolvable URL can become domain candidates.
- `github`: GitHub repository search; uses an optional `GITHUB_TOKEN` for rate limits and only accepts repositories with a usable homepage or public repository URL.

Provider failures are isolated, never converted into fabricated domains, and summarized in collection logs via `provider_stats`.

## Collection options

```json
{
  "providers": ["local_seed", "google", "wikidata", "github"],
  "max_results": 500,
  "max_queries": 24,
  "include_domains": ["example.com"],
  "exclude_domains": ["spam.example"],
  "language": "en",
  "seed_urls": ["https://example.com"]
}
```

Secrets are read only from backend environment variables and are never included in logs or API responses.
