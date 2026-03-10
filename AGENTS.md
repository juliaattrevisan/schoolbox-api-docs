# Schoolbox – Agent Guide

## Overview

Schoolbox is a school management platform combining LMS and SIS functionality.
It is deployed internally and not shipped in client packages.

- **Backend:** PHP 8.4
- **Frontend:** Vue 3 + TypeScript
- **Data stores:** MySQL/MariaDB, Redis
- **Deployment:** APT packages managed via Puppet

---

## Architecture

- **MVC** — controllers defined in `conf/`; see `docs/mvc/readme.md`
- **Aura framework** — dependency injection, routing, session management
- **Frontend build** — Rspack (modern Webpack replacement); see `docs/front-end/build-process.md`
- **Real-time messaging** — AMQP + STOMP + Ratchet WebSockets

---

## Tech Stack

| Layer | Technologies |
|-------|-------------|
| Backend | PHP 8.4, Aura (DI/Router/Session), Symfony (HttpFoundation/Cache/Mailer/Lock), Guzzle, Twig, Flysystem, PDO (MySQL), Predis (Redis) |
| Frontend | Vue 3, TypeScript, Rspack, SCSS, CKEditor |
| Integrations | OAuth2, LTI 1.3, Firebase, Canvas API, SAML, WebDAV (Sabre), IMAP, Gotenberg |

---

## Project Structure

| Directory | Purpose |
|-----------|---------|
| `conf/` | Aura MVC module bootstrap / controller config |
| `www/` | Web root; one subdirectory per module |
| `library/` | Core business logic |
| `vue/` | Shared Vue 3 components, composables, mixins |
| `resources/` | Static assets (icon fonts, etc.) |
| `templates/` | Default and per-client skins |
| `tools/` | CLI scripts, daemons, build/maintenance tools |
| `packages/` | Curriculum package definitions |
| `test/` | Codeception, Playwright, and Behat test suites |
| `sql/` | Initial schema; `updates/` for per-release migrations |
| `docs/` | Developer documentation |
| `vendor/` | Composer dependencies — do not edit |
| `node_modules/` | NPM dependencies — do not edit |

---

## Development Commands

### Frontend

```bash
npm run build           # dev build (Rspack)
npm run publish         # production build
npm run watch           # watch mode
npm run lint            # ESLint (must pass with 0 warnings)
npm run prettier:write  # auto-format JS/TS/Vue/CSS/SCSS
npm run typecheck       # vue-tsc type check
npm test                # Jest unit tests
npm run watch-test      # Jest watch mode
```

### Backend

```bash
./vendor/bin/phpcs      # PSR-12 style check
./vendor/bin/phpcbf     # auto-fix style issues
./vendor/bin/phpstan    # static analysis (level 4)
./vendor/bin/phpunit    # unit tests
```

---

## Testing

| Suite | Tool | Config file |
|-------|------|-------------|
| PHP unit | PHPUnit | `phpunit.xml` |
| Acceptance / integration | Codeception | `codeception.dist.yml` |
| Behaviour | Behat | `behat.yml` (tests in `test/behavior/`) |
| JS unit | Jest | `jest.config.js` (happy-dom environment) |
| E2E browser | Playwright | `playwright.config.ts` |

Run the relevant suite(s) for changed code before marking work done.

---

## Code Standards

### PHP

- Style: PSR-12 enforced via PHPCS (`phpcs.xml`)
- Static analysis: PHPStan level 4 (`phpstan.neon`, `phpstan-baseline.neon`)

### JS / TS / Vue

- Linting: Airbnb + TypeScript ESLint rules (`eslint.config.ts`)
- Formatting: Prettier
- Zero ESLint warnings allowed (`--max-warnings 0`)
- No TypeScript `any` without explicit justification

---

## Git Conventions

### Branch naming

```
task-develop/SA-XXXXX     feature work targeting develop
task-master/SA-XXXXX      feature work targeting master
bug-develop/SA-XXXXX      bug fix targeting develop
bug-master/SA-XXXXX       bug fix targeting master
hotfix-develop/SA-XXXXX   urgent fix
```

### Commit format

```
Short imperative description [SA-XXXXX]
```

### Pull requests

- Default target branch: `develop`
- Some tickets require two PRs (one for `develop`, one for `master`)
- PRs must be reviewed by a different developer before merge

---

## Legacy & Deprecated Patterns

Avoid introducing these patterns in new code. When touching files that use them, prefer migrating away where it is within the scope of the ticket.

### `SitePage` (`library/SitePage.php`)

A monolithic legacy class that mixes routing, session management, template rendering, and HTTP concerns in a single object. It predates the current MVC architecture and is tied to the old PHPLib templating engine.

- **Do not** use `SitePage` in new controllers or pages
- New code should use the Aura-based MVC stack (controllers in `conf/`, `WebRequest\Kernel` for request handling)
- Existing `SitePage`-based pages are candidates for migration; migrate within ticket scope when practical

### jQuery

jQuery is being removed in favour of modern vanilla JS and Vue 3. Do not add new jQuery usage. Replace jQuery calls when touching existing code where it is within ticket scope.

---

## Agent Guidelines

### Do

- Run the relevant linter and tests before considering a task complete
- Scope changes to the ticket — one concern per PR
- Prefer editing existing files over creating new ones
- Follow existing patterns in the module you're working in
- Use `phpstan-baseline.neon` for pre-existing PHPStan issues; do not suppress new ones

### Avoid

- Editing `vendor/` or `node_modules/` directly
- Committing `.env` files, secrets, or credentials
- Refactoring code outside the scope of the current ticket
- Adding comments or docblocks to code you didn't change
- Adding error handling for scenarios that can't happen
- Over-engineering: no premature abstractions, no hypothetical future requirements
- Skipping pre-commit hooks (`--no-verify`)
- Force-pushing to `develop` or `master`
