# Contributing to apinator/apinator-php

## Development Setup

```bash
git clone https://github.com/apinator/sdk-php.git
cd sdk-php
composer install
vendor/bin/phpunit
```

## Code Standards

- PHP 8.1+ with strict types
- Zero external dependencies — stdlib only
- All public methods must have PHPDoc comments
- PSR-4 autoloading
- 85%+ test coverage

## Commit Format

We use [Conventional Commits](https://www.conventionalcommits.org/):

```
feat(php): add batch event triggering
fix(php): correct webhook timestamp validation
docs(php): update Laravel integration guide
test(php): add coverage for channel auth edge cases
chore(php): update PHPUnit to v11
```

## Pull Request Process

1. Fork the repo and create a feature branch from `main`
2. Write tests for any new functionality
3. Run the test suite: `vendor/bin/phpunit`
4. Update documentation if you changed public APIs
5. Submit a PR with a clear description of what and why

## Architecture

See [docs/architecture.md](docs/architecture.md) for an overview of the codebase structure.

## Reporting Issues

Use [GitHub Issues](https://github.com/apinator/sdk-php/issues) with the provided templates.
