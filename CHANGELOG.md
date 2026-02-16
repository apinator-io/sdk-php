# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-02-15

### Added
- `RealtimeClient` with HMAC-authenticated API requests
- Event triggering on single or multiple channels
- Channel authentication for private and presence channels
- Webhook signature verification with timestamp freshness check
- Channel introspection (list channels, get channel info)
- Exception hierarchy: `RealtimeException`, `ApiException`, `AuthenticationException`, `ValidationException`
- Zero external dependencies — PHP 8.1+ stdlib only
- PHPUnit 10 test suite
