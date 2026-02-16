# Architecture

Contributor guide to the `apinator/apinator-php` internals.

## Source Layout

```
src/
├── Apinator.php            — Main client (orchestrates auth + HTTP requests)
├── Auth.php                — HMAC signing (request + channel auth)
├── Webhook.php             — Webhook signature verification
└── Errors/
    ├── RealtimeException.php      — Base exception
    ├── ApiException.php           — API request failures
    ├── AuthenticationException.php — 401/403 errors
    └── ValidationException.php    — Invalid input / webhook failures
```

## Request Signing

Every API request is HMAC-signed:

```
body_md5 = empty_body ? "" : md5(body)
sig_string = "{timestamp}\n{METHOD}\n{path}\n{body_md5}"
signature = hmac_sha256(secret, sig_string)
```

Headers: `X-Realtime-Key`, `X-Realtime-Timestamp`, `X-Realtime-Signature`.

## Channel Authentication

For private/presence channels, the client signs:

```
sig_string = "{socket_id}:{channel_name}"           // private
sig_string = "{socket_id}:{channel_name}:{data}"    // presence
auth = "{api_key}:{hmac_sha256(secret, sig_string)}"
```

## HTTP Client

Uses `file_get_contents` with `stream_context_create` — no cURL dependency. The `$http_response_header` magic variable captures response headers.

## Exception Hierarchy

```
RealtimeException (base)
├── ApiException         — network/HTTP errors, carries status code + response body
├── AuthenticationException — 401/403 specifically
└── ValidationException  — input validation, webhook verification failures
```

## Design Principles

- **Zero dependencies**: PHP stdlib only (`hash_hmac`, `json_encode`, `file_get_contents`)
- **Named parameters**: PHP 8.1 constructor promotion for clean API
- **Static helpers**: `Auth` and `Webhook` can be used standalone without `Apinator`
- **Constant-time comparison**: `hash_equals` for all signature verification
