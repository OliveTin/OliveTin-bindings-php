# OliveTin PHP API client

Small PHP bindings for the [OliveTin](https://github.com/OliveTin/OliveTin) **Connect RPC** HTTP JSON API. Supported calls include **`Init`** (connection and auth check), plus **starting actions** (fire-and-forget and wait-for-completion).

## Links

- **[Packagist package](https://packagist.org/packages/jwread/olivetin-bindings-php)** — Composer metadata and releases
- **[OliveTin (upstream)](https://github.com/OliveTin/OliveTin)** — main OliveTin server repository

## Requirements

- PHP 8.1+
- Extensions: `json`, `curl`

## Install

From the repository root containing `php/`:

```bash
composer require jwread/olivetin-bindings-php
```

Or add a path repository pointing at `./php` and require it locally (`jwread/olivetin-bindings-php:@dev`).

## Authentication

This client supports **one** credential style: an HTTP bearer token sent as:

```http
Authorization: Bearer <your-token>
```

How OliveTin interprets that token depends on your server configuration:

- With **JWT** auth (`authJwtHmacSecret`, JWKS, or a public key), pass a valid JWT string as the token (still sent as `Bearer`).
- With **trusted reverse proxies**, validate your own API key at the proxy and translate successful requests into headers OliveTin trusts (`authHttpHeaderUsername`, etc.). In that setup your PHP code might still send `Bearer <api-key>` to the proxy only—never expose OliveTin directly without TLS and proper validation.

OAuth flows, cookies, and local username/password login are intentionally **not** implemented here.

## Usage

Base URL should be the OliveTin web root (same host/port as the UI), **without** the `/api` suffix—the client adds `/api` by default.

```php
<?php

use OliveTin\Api\OliveTinClient;
use OliveTin\Api\OliveTinApiException;

$client = new OliveTinClient('http://127.0.0.1:1337', getenv('OLIVETIN_TOKEN'));

try {
    $client->init();

    $started = $client->startAction(
        bindingId: 'your-binding-id',
        arguments: ['message' => 'hello'],
    );
    echo $started['executionTrackingId'];

    $log = $client->startActionAndWait(
        actionId: 'your-action-id',
        arguments: [],
    );
    echo $log['output'];
} catch (OliveTinApiException $e) {
    fwrite(STDERR, $e->getMessage() . ' (HTTP ' . $e->httpStatus() . ")\n");
}
```

### Custom API prefix

If OliveTin is mounted elsewhere than `/api`:

```php
new OliveTinClient('https://example.com', $token, apiPrefix: '/olivetin/api');
```

## RPC coverage

| Method | Purpose |
|--------|---------|
| `init` | Bootstrap / session metadata; use to verify the server and bearer token |
| `startAction` | Start using **binding id** + optional arguments |
| `startActionAndWait` | Start using **action id**, block until finished |

Protobuf JSON uses **camelCase** field names (for example `executionTrackingId`, `bindingId`).

## CI, docs, and releases

CI and publishing follow the same shape as [jamesread/libAllure](https://github.com/jamesread/libAllure), with a few adjustments for safer defaults:

- **`.github/workflows/release.yml`** — PHP matrix (`composer update` with `prefer-lowest` / `prefer-stable`), then `make phpstan`, `make lint`, and `make tests`, plus **`composer audit`** on one representative cell (PHP 8.4 + `prefer-stable`). The **`release`** job runs only on **`push` to `main`**, checks out **full history** (`fetch-depth: 0`), and runs [semantic-release](https://semantic-release.gitbook.io/) from `.releaserc.yaml` (GitHub releases from [Conventional Commits](https://www.conventionalcommits.org/)).
- **`GITHUB_TOKEN` vs PAT** — the release step uses the workflow **`GITHUB_TOKEN`** with explicit job permissions (`contents`, `issues`, `pull-requests`: **write**), matching upstream semantic-release guidance. That is enough for releases **in this repository**. Use a PAT (for example libAllure’s `CONTAINER_TOKEN`) only if you need behaviour `GITHUB_TOKEN` cannot provide (examples: releasing from **another** repo, bypassing **branch protection** without “GitHub Actions” bypass rules, or triggering downstream workflows that ignore `GITHUB_TOKEN` events).
- **`.github/workflows/doxygen.yml`** — builds HTML API docs with Doxygen (`Doxyfile`, output under `api-docs/`) and deploys them to **GitHub Pages** when `main` is pushed (enable Pages with the **GitHub Actions** source in the repo settings). The **`github-pages`** environment must exist (GitHub creates it on first Pages deploy); restricted deployment protections may require approval on the first run.

Local equivalents:

```bash
composer install
make            # tests + lint
make phpstan
make docs       # requires `doxygen` on PATH
```

## License

Apache-2.0 (same family as OliveTin).
