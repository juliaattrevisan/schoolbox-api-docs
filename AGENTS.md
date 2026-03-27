# Schoolbox – Agent Guide

## Overview

Schoolbox is a school management platform combining LMS and SIS functionality.
It is deployed internally and not shipped in client packages.

- **Backend:** PHP 8.4
- **Frontend:** Vue 3 + TypeScript
- **Data stores:** MariaDB 11.8, Redis 6.0
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

| Layer        | Technologies                                                                                                                        |
|--------------|-------------------------------------------------------------------------------------------------------------------------------------|
| Backend      | PHP 8.4, Aura (DI/Router/Session), Symfony (HttpFoundation/Cache/Mailer/Lock), Guzzle, Twig, Flysystem, PDO (MySQL), Predis (Redis) |
| Frontend     | Vue 3, TypeScript, Rspack, SCSS, CKEditor                                                                                           |
| Integrations | OAuth2, LTI 1.3, Firebase, Canvas API, SAML, WebDAV (Sabre), IMAP, Gotenberg                                                        |

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

In order to ensure frontend code quality, these commands should be run with the list of files which have changed:
```bash
npm run build                  # verify that frontend code still compiles after files have changed
npx eslint [FILE]...           # verify file still passes ESLint
npx prettier --check [FILE]... # verify that file still meets auto-format standards
npx prettier --write [FILE]... # auto-format file
npm test                       # verify all unit tests still pass, not just those changed
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

In order to ensure backend code quality, these commands should be run with the list of files which have changed:
```bash
./vendor/bin/phpcs [FILE]...           # verify that one or more files meet PSR-12
./vendor/bin/phpcbf [FILE]...          # auto-fix style issues in these files
./vendor/bin/phpstan analyze [FILE]... # ensure that files pass static analysis
./vendor/bin/phpunit                   # verify all unit tests still pass, not just those changed
```

---

## Testing

| Suite       | Tool        | Config file            | Environment       | Test file location                           | Scope                                             |
|-------------|-------------|------------------------|-------------------|----------------------------------------------|---------------------------------------------------|
| PHP unit    | PHPUnit     | `phpunit.xml`          | CLI               | Alongside PHP code, in `Test` folders        | PHP code at class and function level              |
| Smoke, API  | Codeception | `codeception.dist.yml` | PhpBrowser        | `test/codeception`                           | Browser requests (does not test user interaction) |
| JS unit     | Jest        | `jest.config.js`       | happy-dom         | Alongside JS/TS/Vue code, in `tests` folders | JS code at class and function level               |
| E2E browser | Playwright  | `playwright.config.ts` | headless Chromium | `test/playwright`                            | User interaction                                  |

Run the relevant suite(s) for changed code before marking work done.

---

## Code Standards

When writing or reviewing code, take reasonable steps to ensure that these standards are met: if they are not, then a justification is required of why they cannot be met.

For example: if written code contains a performance issue which cannot be addressed without significant architectural change, then this should be mentioned.

### PHP

- Style: PSR-12 enforced via PHPCS (`phpcs.xml`)
- Static analysis: PHPStan level 4 (`phpstan.neon`, `phpstan-baseline.neon`)
- Covered by PHPUnit tests, and designed in a manner which allows individual methods/functions/classes to be tested in isolation

### JS / TS / Vue

- Linting: Airbnb + TypeScript ESLint rules (`eslint.config.ts`)
- Formatting: Prettier
- Zero ESLint warnings allowed (`--max-warnings 0`)
- No TypeScript `any` without explicit justification

### Quality

- When writing code, prioritise these attributes in order:
  - simplicity: simpler code is easier to understand for developers and agents, and easier to maintain and refactor
  - correctness: once common cases are correctly handled in a simple manner, edge cases can be addressed
  - performance: once code is simple and correct, it can be optimised
- Ensure that code written does not introduce security issues, for example:
  - insufficient access control
  - vulnerable dependencies
  - injection of untrusted input (SQL injection, XSS)
  - privacy concerns (leakage or unnecessary storage of PII)
  - hard-coded API keys or other tokens
  - other risks as defined in OWASP

---

## Version Numbering

Schoolbox version numbers are numbered thus:
```
{yearOfMajorVersion}.{majorVersionNumberForYear}.{patchVersion}
```

where:
- `{yearOfMajorVersion}` is the year in which this major version was or will be released:
  - this does not always match the current year, as this major version could've been released in a previous year
- `{majorVersionNumberForYear}` is the numbered major version for `{yearOfMajorVersion}`, starting from 0
- `{patchVersion}` is the patch number for `{yearOfMajorVersion}.{majorVersionNumberForYear}`:
  - for generally available releases, this is a number starting from 0
  - for preview versions deployed to staging instances only, this is `beta{version}`, `{version}` starting from 1
  - for pre-release versions, this is `alpha{version}`, `{version}` starting from 1

### Examples

- `26.0.1` is the 2nd patch for the 1st major version released in 2026
  - remember, major and patch version counts start from 0
- `25.1.0` is the 1st patch for the 2nd major version released in 2025
- `26.1.alpha1` is the 1st pre-release alpha for the 2nd major version released in 2026
  - remember, alpha and beta version counts start from 1
- `26.1.beta2` is the 2nd preview version for the 2nd major version released in 2026

---

## Database Schema changes

Schema change scripts belong in `updates/{versionNumber}`.

`versionNumber` will match the next version number to be released, as per Version Numbering above:
- for code in both `master` and `develop`, this will be a generally available release version such as `26.0.1`
- for code in `develop` only, this will be a pre-release or preview version, such as `26.1.alpha1` or `26.1.beta2`

The exact version number will be determined as part of the release process - confirm with the team if unsure.

Within the `updates/{versionNumber}` folder, the update script itself:
- should (but does not need to) belong in a sub-folder
- must be a `.php` or `.sql` file: `.php` will be executed, `.sql` will be run against the Schoolbox instance's database

Update scripts will be run in alpha-numeric order: thus, if a script needs to run before another it may be given a numeric prefix. For example, `00-prepare.sql` will run before `01-execute.sql`.

---

## Git Conventions

### Base branches

- `master`: current main branch, production versions are based off of this branch
- `develop`: future development branch, beta versions are based off this branch:
  - when a new major version is created:
    - a branch referencing the old major version is created from `master`
    - the old `master` branch is replaced with a new `master` branch created from `develop`

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

Optional detailed description of the commit

# Commits created by a coding agent may include the following line:
Co-Authored-By: <agent>
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

### `DeprecatedModel` (`library/DeprecatedModel.php`)

A historic database access class, which predates the Mapper/Model architecture in `library/Schoolbox/Mapper.php`.

Where practical, replace usage of `DeprecatedModel` subclasses with their equivalent `Mapper`:
- the `$_table` property sets the table in `DeprecatedModel`: the `Mapper` equivalent is a call to the `hasTable()` method

### jQuery

jQuery is being removed in favour of modern vanilla JS and Vue 3. Do not add new jQuery usage. Replace jQuery calls when touching existing code where it is within ticket scope.

---

## Agent Rules and Guidelines

Read and understand all the rules and guidelines below, before writing any code.

### Rules

- When working on this project, do not modify files outside of this project
- Only make changes that are directly requested. Do not refactor, restructure, or clean up code beyond the scope of the task, unless explicitly asked to do so.
- Do not guess or assume business requirements. If the intended behaviour of a new or changed feature is unclear, ask before writing code. (It is fine to infer what existing code does — only the requirements for new work need clarification.)
- Avoid writing files to disk during web requests (containers do not share filesystems — use temp files only)
- Avoid editing `vendor/` or `node_modules/` directly: these are managed by Composer and NPM directly
- Avoid committing `.env` files, secrets, or credentials
- Avoid adding comments or docblocks to code you didn't change
- Do not commit to base branches such as `develop` or `master` directly: always create a named branch first
- Avoid skipping pre-commit hooks (`--no-verify`)

### Guidelines

- Before writing code, consider its impact: performance, memory usage, and efficiency. Flag any concerns before proceeding.
- Prefer editing existing files over creating new ones
- Follow existing patterns in the module you're working in
- Use `phpstan-baseline.neon` for pre-existing PHPStan issues; do not suppress new ones
- If a PHPStan issue cannot be avoided, a `@phpstan-ignore-next-line - <explanation>` comment is acceptable
- Run the relevant linter and tests before considering a task complete
- Scope changes to the ticket — one concern per PR
- Avoid over-engineering: no premature abstractions, no hypothetical future requirements
- Avoid adding error handling for scenarios that can't happen
- If you discover that tech stack, tooling, deprecated patterns, or project structure information is missing or out of date, propose an update to this file as part of your PR
