# SA-22393 IB Feature Flag Design

## Context

SA-22393 asks for a hidden feature flag for the International Baccalaureate module. The flag must be triggered through Instance Registry using the Config API, be available on all plans, and make IB-specific features available when enabled.

The current branch already contains substantial IB work in `updates/26.1.alpha1/IB`, including the IB admin module, IB configuration entries, IB candidate number field, IB Coordinator service permission, IB qualification tables, and IB registration functionality. The feature flag should control exposure of those surfaces, not whether the underlying schema exists.

The flag must be reversible. Turning it off should hide IB-specific surfaces again, while leaving existing IB data, permission assignments, package install state, and package-created curriculum or badge content untouched.

## Goals

- Add one hidden `ib_enabled` config as the source of truth for IB module exposure.
- Allow Instance Registry to activate and deactivate the flag through the Config API.
- Hide IB admin routes, navigation, profile/admin fields, service permission surfaces, and IB package install availability when the flag is disabled.
- Show those same surfaces when the flag is enabled and the user has the existing required access.
- Keep existing IB data intact when the flag is disabled.

## Non-Goals

- Do not delete IB data when the flag is disabled.
- Do not uninstall IB packages when the flag is disabled.
- Do not remove package-created curriculum or badge content when the flag is disabled.
- Do not permanently flip IB package rows to `hidden = false` in a one-way migration.
- Do not change business behavior inside the IB registration workflow beyond hiding or showing access to it.

## Architecture

Add `ConfigMapper::NAME_IB_ENABLED = 'ib_enabled'`.

Create a hidden Modules config row:

- `name`: `ib_enabled`
- `value`: false
- config type: `Modules`
- field: boolean
- `hidden`: true
- `editable`: false
- `module`: `settings`

The setting should be included in `ConfigMapper::SETTINGS_AFFECTING_MODULES` so route and module caches rebuild when it changes.

`ib_enabled` is the source of truth for runtime exposure. Code that decides whether IB surfaces appear should read this config rather than permanently mutating database rows during activation.

## Components

### Admin Module Routes

In `conf/modules/Admin.php`, only load `Schoolbox\Admin\InternationalBaccalaureate\Module` when `ConfigMapper::NAME_IB_ENABLED` is true.

This hides IB routes after the module cache is rebuilt.

### Admin Navigation

In `Schoolbox\Admin\Navigation\Subnav`, show the `IB` admin navigation group only when:

- `ib_enabled` is true, and
- the user is a superuser or has the IB Coordinator service permission.

Existing permission checks remain in place.

### Package Admin

The package table should continue to store the listed IB packages as hidden by default. The package listing behavior should include the named IB packages only when `ib_enabled` is true.

IB package locations:

- `IBPYPTransDis2025`
- `IBDPCore`
- `IBProgramme`
- `IBPYPProgramme`
- `IBMYPProgramme`
- `IBBadges`

When `ib_enabled` is false, those packages should not appear in `/adminv2/package` even if they exist in the database. Other hidden packages must remain hidden.

### Profile And Admin User Fields

Hide IB-specific user/profile fields when `ib_enabled` is false:

- `qualificationCollection`
- `ibCandidateNumber`

Existing stored values remain unchanged.

### Service Permission Surfaces

Filter the `IB Coordinator` service permission out of admin permission assignment lists when `ib_enabled` is false.

Existing assignments remain unchanged. If the flag is re-enabled, previous assignments can become visible and effective again according to the existing permission checks.

## Data And Migrations

Add the `ib_enabled` config row under `updates/26.1.alpha1/IB`, alongside the existing IB migrations for this feature set.

Update IB qualification seed data to match SA-22393:

- `Diploma Programme`, linked to system purpose `Diploma Programme Year 2` and category `DIPLOMA`
- `Diploma Course`, linked to system purpose `Diploma Course` and category `COURSE`

The current migration seeds `DP Year 1`, `DP Year 2`, and `VCE`; that should be adjusted or superseded depending on whether the target update scripts have already run in any environment.

No migration should make IB package visibility permanently irreversible. Package visibility should be governed by runtime checks against `ib_enabled`.

## Config API

Instance Registry is expected to toggle `ib_enabled` through the Config API.

The existing Config API authorization helper allows Instance Registry tokens to access and edit hidden and non-editable configs. Superusers should not be able to edit `ib_enabled` through the API because it is hidden and non-editable.

The Config API controller should detect when a saved config name is in `ConfigMapper::SETTINGS_AFFECTING_MODULES` and rebuild the route/module cache after saving. This must work for:

- single config PATCH
- bulk config PATCH

Keep the existing JSON response shape for single and bulk PATCH requests.

## Reversibility

When changed from disabled to enabled:

- IB routes become available after module cache rebuild.
- IB admin navigation appears for allowed users.
- IB profile/admin permission surfaces appear.
- Listed IB packages appear in `/adminv2/package`.

When changed from enabled to disabled:

- IB routes disappear after module cache rebuild.
- IB admin navigation disappears.
- IB profile/admin permission surfaces are hidden.
- Listed IB packages disappear from `/adminv2/package`.
- Existing IB data, installed package state, package-created content, and existing permission assignments remain untouched.

## Testing

Add focused tests for the changed behavior.

Config and migration checks:

- `ib_enabled` exists, defaults to false, is hidden, and belongs to the Modules config type.
- `ConfigMapper::SETTINGS_AFFECTING_MODULES` includes `ib_enabled`.

Module and navigation:

- IB admin routes/menu are absent when `ib_enabled` is false.
- IB admin routes/menu are present when `ib_enabled` is true and the user has the required access.

Config API:

- Instance Registry token can edit hidden `ib_enabled`.
- Superuser cannot edit hidden `ib_enabled`.
- Single PATCH and bulk PATCH detect module-affecting changes and rebuild module cache.

Packages:

- IB package locations are not returned when `ib_enabled` is false.
- IB package locations are returned when `ib_enabled` is true.
- Other hidden packages remain hidden.

Profile and permissions:

- IB candidate number and qualification profile/admin fields are hidden when `ib_enabled` is false and shown when true.
- IB Coordinator permission surfaces are hidden when `ib_enabled` is false and shown when true.

Verification should include targeted PHPUnit for changed backend classes, PHPCS and PHPStan on changed PHP files, and final authoritative backend verification inside `docker/ssh` when Docker is available.
