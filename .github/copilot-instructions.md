# Schoolbox Copilot Instructions

## Repository Overview

Schoolbox is a comprehensive Learning Management System (LMS) built in PHP with Vue.js frontend. It serves K-12 schools with course management, assessments, portfolios, and extensive integrations.

**Size**: Large monorepo (~200MB) with 25+ modules  
**Stack**: PHP 8.4, Node.js 20.x, Vue.js 3, MariaDB 11.4, Docker/Vagrant  
**Architecture**: MVC with Aura DI/Router, modular plugin system  

## Project Architecture

### Core Directories
```
* /conf/ - initialisation code for Schoolbox modules
* /debian/ - APT package building definitions (not deployed to clients)
* /docker/ - definition of Docker images used in Schoolbox development and production environments
* /docs/ - documentation for developers
* /library/ - Schoolbox code base
* /library/Schoolbox - Schoolbox Modules
* /library/Alaress - Company wide standard libraries
* /node_modules/ - directory for external libraries loaded via NPM
* /templates/ - legacy PHPTemplate and twig html templates and per client overrides
* /test/ - Codeception (api, smoke tests and integration tests), playright tests (acceptance tests)
* /tools/ - Build, update and maintenance tools. Also includes cli scripts for sync.
* /updates/ - per release update files, primarily sql schema changes
* /www/ - web root directory
* /www/static - web root directory for static assets (JS, CSS, images) only
* /vue/ - directory for storing Vue components that are not directly related to a
  module within `library/`
```

### Overview of Terms
* *Module.* It encapsulates Routing, Permissions and Preloads. Respectively: determines which Controller method should execute based on the route provided, whether the Controller method has the permissions to execute, and what objects it needs to execute.
* *Controller.* Provides details about the web request to business logic contained in Supporting Classes. Controller arranges the output data for the View to render (in this case, twig). 
* *Supporting Classes.* Additional high-cohesion classes that contains lower-level details, hiding this from the main Controller methods. Utilises Mappers and Models.
* *Mapper.* A database abstraction. Gets data out of the database into 'Models' and vice versa. Typically contains other database queries that are commonly used for getting collections of Models.
* *Model.* A representation of an object from the database. They can only be persisted by the 'Mapper', but you can ask them about their properties and relations and other relevant models.
* *View.* Renders the output of the Controller method so the user can see it in their browser, typically handled with with Twig for HTML or JSON for APIs.

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
**PHP version**: Composer requires exactly 8.4.x  
**Playwright**: Requires TZ='Australia/Melbourne' for date tests  
**Performance**: Disable xdebug for paratest if slow  

### Environment Setup
**Docker** (recommended): `sudo docker/up && sudo docker/provision`  
**Vagrant** (legacy): `vagrant up && vagrant provision`  
Both need: `127.0.0.1 docker.dev.schoolbox.cloud` in `/etc/hosts`

### Required Services for Full Testing
MySQL/MariaDB, RabbitMQ, Redis. Use `INSTANCE_ID=smoketest` for tests.


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
6. `vendor/bin/phpstan analyze`
7. `npm run build` (for UI changes)

## Commits and PR's
- all commits should append the jira ticket number in the first line
  `this is a commit message [ticket number]`
  eg. `double click to open feedback UI [SA-20827]`
- all PR's title's should append the  jira ticket number

## Code Reviews
- When performing a code review, check for OWASP security issues.
- When performing a code review, that the code is performant and efficent, without undue complexity.
- Wehn performing a code review, ensure that code changes are inline with standard practices, code styles and naming conventions.

## Trust These Instructions
These are comprehensive and tested. Only search further if commands fail unexpectedly or working with unlisted modules. The build process is reliable when followed exactly - most failures are environment setup or sequence issues.
