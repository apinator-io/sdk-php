# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.0.2](https://github.com/apinator-io/sdk-php/compare/v1.0.1...v1.0.2) (2026-02-16)


### Bug Fixes

* update README ([35b98b2](https://github.com/apinator-io/sdk-php/commit/35b98b2fd4747604cecb2f1ad6c0007163de84e7))

## [1.0.1](https://github.com/apinator-io/sdk-php/compare/v1.0.0...v1.0.1) (2026-02-16)


### Bug Fixes

* update README ([f35f768](https://github.com/apinator-io/sdk-php/commit/f35f768e9338e5ca5320112e5c7262d7816561b0))

## 1.0.0 (2026-02-16)


### Bug Fixes

* update tests ([09a3a84](https://github.com/apinator-io/sdk-php/commit/09a3a847dab97b8b941597b28974c6668edc8213))
* update tests ([999fa50](https://github.com/apinator-io/sdk-php/commit/999fa509deb2246f805c35267bc12bff7454f044))


### Miscellaneous Chores

* init ([078c383](https://github.com/apinator-io/sdk-php/commit/078c383114f6de80be119ac4dca51b99c2eec7e8))

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
