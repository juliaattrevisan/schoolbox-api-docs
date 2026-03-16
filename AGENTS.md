# Schoolbox – Agent Guide

## Overview

Schoolbox is a school management platform combining LMS and SIS functionality.
It is deployed internally and not shipped in client packages.

- **Backend:** PHP (version as per `composer.json`; expected to track an actively supported version — see [php.net/supported-versions](https://www.php.net/supported-versions.php))
- **Frontend:** Vue 3 + TypeScript
- **Data stores:** MySQL/MariaDB, Redis
- **Deployment:** two environments:
  - **client-hosted** — school's own infrastructure (cloud or physical servers); deployed as APT packages managed via Puppet
  - **cloud-saas** — Schoolbox-managed cloud infrastructure; hybrid: web requests served by container images built via GitHub Actions, background tasks and daemons run on a Puppet-managed server

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

| Directory       | Purpose                                                  |
|-----------------|----------------------------------------------------------|
| `conf/`         | Aura MVC module bootstrap / controller config            |
| `docs/`         | Developer documentation                                  |
| `debian/`       | APT package build specification                          |
| `docker/`       | Docker-based development environment build specification |
| `library/`      | Core business logic                                      |
| `node_modules/` | NPM dependencies — do not edit                           |
| `packages/`     | Curriculum and badge package definitions                 |
| `patches/`      | Hot-patches for Composer packages which require them     |
| `resources/`    | Static assets (icon fonts, etc.)                         |
| `sql/`          | Initial schema                                           |
| `stories/`      | Examples of UI components, written in Storybook          |
| `templates/`    | Default and per-client skins                             |
| `test/`         | Codeception and Playwright test suites                   |
| `tools/`        | CLI scripts, daemons, build/maintenance tools            |
| `types/`        | Typescript type definitions                              |
| `updates/`      | Per-release migrations                                   |
| `vendor/`       | Composer dependencies — do not edit                      |
| `vue/`          | Shared Vue 3 components, composables, mixins             |
| `www/`          | Web root; one subdirectory per module                    |

---

## Development Commands

### Starting the development environment

At its simplest, the Docker-based development environment can be started with:

```bash
docker/up
docker/provision
```

These scripts wrap around `docker compose`:
* `docker/up` builds a set of environment variables required for starting the environment, before starting it
* `docker/provision` loads the initial data set and applies any updates required in `updates/`

### Frontend

These commands should be run within the Docker environment's CLI:
```bash
docker/ssh
```

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

These commands should be run within the Docker environment's CLI:
```bash
docker/ssh
```

```bash
./vendor/bin/phpcs      # PSR-12 style check
./vendor/bin/phpcbf     # auto-fix style issues
./vendor/bin/phpstan    # static analysis (level 4)
./vendor/bin/phpunit    # unit tests
```

---

## Testing

| Suite                    | Tool        | Config file                              |
|--------------------------|-------------|------------------------------------------|
| PHP unit                 | PHPUnit     | `phpunit.xml`                            |
| Acceptance / integration | Codeception | `codeception.dist.yml`                   |
| JS unit                  | Jest        | `jest.config.js` (happy-dom environment) |
| E2E browser              | Playwright  | `playwright.config.ts`                   |

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

### Base branches

- `master`: current main branch, production versions are based off of this branch
- `develop`: future development branch, beta versions are based off this branch:
  - when a new major version is created:
    - a branch referencing the old major version is created from `master`
    - the old `master` branch is replaced with a new `master` branch created from `develop`

### Version naming

Schoolbox version numbers are numbered thus:
```
{yearOfMajorVersion}.{majorVersionNumberForYear}.{patchVersion}
```

#### Examples

- `26.0.1` is the 2nd patch for the 1st major version released in 2026 (counting starts from 0)
- `25.1.0` is the 1st patch for the 2nd major version released in 2025

Note that `{yearOfMajorVersion}` does not always match the current year: the major version could've been released in a previous year.

### Branch naming

```
task-develop/SA-XXXXX           feature work targeting develop
task-master/SA-XXXXX            feature work targeting master
{bug,hotfix}-develop/SA-XXXXX   bug fix targeting develop
{bug,hotfix}-master/SA-XXXXX    bug fix targeting master
```

### Commit format

```
Short imperative description [SA-XXXXX]
```

### Pull requests

- Features usually require only a `develop` PR: occasionally they will also require a `master` PR
  - There's no set rule for whether a feature is to be released to `master`: this will have been determined by stakeholders
- Bug fixes usually require two PRs (one for `develop`, one for `master`)
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

- Writing files to disk during web requests (containers do not share filesystems — use temp files only)
- Editing `vendor/` or `node_modules/` directly
- Committing `.env` files, secrets, or credentials
- Refactoring code outside the scope of the current ticket
- Adding comments or docblocks to code you didn't change
- Adding error handling for scenarios that can't happen
- Over-engineering: no premature abstractions, no hypothetical future requirements
- Skipping pre-commit hooks (`--no-verify`)
- Force-pushing to `develop` or `master`
