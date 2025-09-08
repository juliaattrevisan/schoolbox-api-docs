# Schoolbox Copilot Instructions

## Repository Overview

Schoolbox is a comprehensive Learning Management System (LMS) built in PHP with Vue.js frontend. It serves educational institutions with course management, assessments, portfolios, and extensive integrations.

**Size**: Large monorepo (~200MB) with 25+ modules  
**Stack**: PHP 8.2/8.4, Node.js 20.x, Vue.js 3, MySQL, Docker/Vagrant  
**Architecture**: MVC with Aura DI/Router, modular plugin system  

## Critical Build Requirements

### Prerequisites
- **PHP**: Exactly 8.2.x or 8.4.x (8.3.x causes composer failures)
- **Node.js**: 20.x, **Composer**: Latest, **MySQL/MariaDB**: For tests

### Essential Build Sequence (ALWAYS follow this order)

1. **Install PHP dependencies:**
   ```bash
   composer install  # Add --no-dev for production
   ```
   **Common failure**: PHP version mismatch

2. **Install Node dependencies:**
   ```bash
   npm ci  # Prefer over npm install for CI
   ```
   **Common failure**: Network issues with cdn.sheetjs.com - retry or use npm install

3. **Build frontend assets:**
   ```bash
   npm run build      # Development (2-5 min)
   npm run publish    # Production minified (3-7 min)
   ```

### Testing Commands (run in this order)

1. **Frontend validation:**
   ```bash
   npm run lint          # ESLint (0 warnings required)
   npm run prettier:check # Formatting
   npm run typecheck     # TypeScript 
   npm test             # Jest unit tests
   ```

2. **PHP validation:**
   ```bash
   vendor/bin/phpcs     # PSR-12 standard
   vendor/bin/paratest -p 4 --configuration phpunit.xml --testsuite default
   vendor/bin/phpstan analyse  # Level 4 static analysis
   ```

3. **Integration tests (require running environment):**
   ```bash
   vendor/bin/codecept run Integration
   INSTANCE_ID=smoketest TZ='Australia/Melbourne' npx playwright test  # 10-45 min
   vendor/bin/behat     # Behavior tests
   ```

## Project Architecture

### Core Directories
```
/library/Schoolbox/     - Main modules (Learning, Assessment, etc.)
/library/Alaress/       - Framework (Database, Auth, etc.)
/www/static/js/css/     - Frontend assets
/vue/                   - Vue.js components with Pinia
/templates/             - Twig templates
/test/                  - PHPUnit, Playwright, Codeception, Behat
/tools/ci/              - Build scripts and CI tools
```

### Key Configuration
- `composer.json` - PHP deps & PSR-4 autoloading
- `package.json` - Node deps & build scripts
- `phpunit.xml` - Unit test config
- `playwright.config.ts` - Acceptance tests (timeout: 5min)
- `webpack.common.js` - Asset building
- `.eslintrc.js` - Airbnb + TypeScript rules

### CI Workflows (.github/workflows/)
- `node.js.yml` - Frontend CI on JS/TS/Vue changes
- `chromatic.yml` - Storybook visual regression
- Docker build workflows for deployment

## Known Issues & Workarounds

**Network failures**: Retry npm commands if external CDNs fail  
**PHP version**: Composer requires exactly 8.2.x or 8.4.x  
**Playwright**: Requires TZ='Australia/Melbourne' for date tests  
**Performance**: Disable xdebug for paratest if slow  

### Environment Setup
**Docker** (recommended): `sudo docker/up && sudo docker/provision`  
**Vagrant** (legacy): `vagrant up && vagrant provision`  
Both need: `127.0.0.1 docker.dev.schoolbox.cloud` in `/etc/hosts`

### Required Services for Full Testing
MySQL/MariaDB, RabbitMQ, Redis. Use `INSTANCE_ID=smoketest` for tests.

## Essential Modules

### Core Learning (`/library/Schoolbox/Learning/`)
Course management, submissions, Turnitin integration, CLI tools in `/tools/`

### Assessment & Curriculum (`/library/Schoolbox/Assessment/`, `/Curriculum/`)
Gradebook, rubrics, reporting with Vue.js frontend

### External Integrations (`/library/External/`)
SIS connectors, LTI, SSO. Config in `/conf/modules/`

### Frontend (`/vue/`, `/www/static/`)
Vue 3 + TypeScript, Webpack build, Jest tests, SCSS with BEM naming

## File Patterns
- **PHP**: PSR-4 autoloading, `*Test.php` for PHPUnit
- **Frontend**: `*.vue` components, `*.spec.ts` (Playwright), `*.test.ts` (Jest)
- **Templates**: Twig inheritance in `/templates/`

## Pre-commit Checklist
1. `npm run lint` (0 warnings)
2. `npm run prettier:check`
3. `npm run typecheck`
4. `npm test`
5. `vendor/bin/phpcs`
6. `vendor/bin/paratest`
7. `npm run build` (for UI changes)

## Trust These Instructions
These are comprehensive and tested. Only search further if commands fail unexpectedly or working with unlisted modules. The build process is reliable when followed exactly - most failures are environment setup or sequence issues.