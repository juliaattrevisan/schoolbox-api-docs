# Skin Config Playwright Acceptance Test Design

## Goal

Add Playwright acceptance coverage for the six primary Sonar UI Skin Config scenarios in SA-22516 while ensuring every test leaves `advanced_ui_enabled` set to `0`.

## Scope

The suite will cover:

1. Colour contrast validation prevents saving until Top Menu Foreground is changed to `rgb(255, 255, 255)`.
2. Container corner radius changes to None and Field corner radius changes to Large, producing `--sbx-config-container-corner-radius: 0` and `--sbx-config-field-corner-radius: 0.75rem` respectively.
3. Container drop shadow changes to Large and `--sbx-config-container-drop-shadow` resolves to the same value as `--sbx-shadow-l`.
4. Custom CSS applies `body { background: green; }` to the previewed page.
5. Undo Changes clears the Custom CSS field and removes the green body background.
6. A saved Accent colour of `rgb(2, 99, 51)` persists on `/pantry/buttons`, affecting the Submit button background and Load more border.

No production behaviour will be changed beyond adding stable `data-test` attributes where the browser investigation shows they are needed.

## Test Structure

Create one spec at `test/playwright/acceptance/admin/design/skinConfig.spec.ts` containing six focused tests. Create one page object at `test/playwright/support/page/admin/design/SkinConfig.ts` for repeated navigation, field interaction, save/undo actions, and CSS custom-property inspection.

The spec will import `test` and `expect` from `@schoolboxTest/playwright/support/integrations`. Its `beforeEach` hook will use `loginAsAdmin`. Each test will be standalone and wrapped in `try/finally`.

At the beginning of each test, update the `config` row named `advanced_ui_enabled` to `1` before visiting Skin Config. In `finally`, restore any Skin Config values persisted by that test and set `advanced_ui_enabled` to `0`. Cleanup will remain inside each test rather than relying on `afterAll`, so a failed scenario does not leave the feature enabled.

## Browser Workflow and Selectors

Before test code is written, reproduce all ticket steps one by one against `https://docker.dev.schoolbox.cloud/` using Playwright MCP and the `sales.tar.gz` dataset. Use discovered `data-test` attributes through `getByTestId`. Where a required control has no suitable test identifier, add a uniquely named feature/field `data-test` attribute to the relevant Vue template and use it from the page object.

The tests will assert observable outcomes rather than implementation-only state:

- validation messages are visible or absent;
- an invalid save does not produce the success state, while a corrected save does;
- computed CSS custom properties resolve to the expected values;
- custom CSS changes computed body styling;
- persisted Accent styling remains after navigation to another page.

## State and Failure Handling

Tests that save values will capture the original relevant `config.value` before modification and restore it during `finally`. Tests that only preview unsaved values will still reset the feature flag in `finally`. Assertions and cleanup will use existing Playwright database and tester fixtures; no new dependencies or general-purpose abstractions will be introduced.

If Playwright MCP cannot complete a browser step within the skill's 15-second limit, implementation will stop without creating or updating test code and the blocking browser error will be reported.

## Verification

After browser reproduction:

1. Run each new test individually with `DOCKER=1 npx playwright test skinConfig.spec.ts --grep <test-name>`.
2. Inspect `test/playwright/_output/` after failures and iterate until each test passes.
3. Run the complete spec with `DOCKER=1 npx playwright test skinConfig.spec.ts`.
4. Run formatting and lint checks for every changed TypeScript/Vue file with nvm loaded.
5. Confirm directly in the database that `advanced_ui_enabled` is `0` after verification.

The final handoff will include the exact Automation Tests coverage entry for Confluence unless the tests have been committed and the page can be updated directly.
