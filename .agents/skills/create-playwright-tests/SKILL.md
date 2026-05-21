---
name: create-playwright-tests
description: Use when the user asks to create or update Schoolbox Playwright acceptance tests based on a scenario.
---

# General

- ONLY create tests when `DATA_FILENAME` in the `docker.env` file is set to `sales.tar.gz`.
- Before creating or updating tests, read the relevant internal documentation:
  - Automation Tests: https://schoolbox.atlassian.net/wiki/spaces/DEV/pages/2047770856/Automation+Tests
  - Automation Tests coverage: https://schoolbox.atlassian.net/wiki/spaces/DEV/pages/2211939023/Automation+Tests+coverage
  - Playwright acceptance tests: https://schoolbox.atlassian.net/wiki/spaces/DEV/pages/3020947462/Playwright+-+Acceptance+Tests
  - Creating new tests: https://schoolbox.atlassian.net/wiki/spaces/DEV/pages/2047770886/How+to+create+new+tests+coding+commits+create+a+PR
  - Adding `data-test`: https://schoolbox.atlassian.net/wiki/spaces/DEV/pages/2631335957/How+to+create+data-test
  - Manually running CI acceptance tests: https://schoolbox.atlassian.net/wiki/spaces/DEV/pages/2626420773/Manually+Running+CI+Acceptance+Tests
- Also read `test/playwright/README.md` before editing tests.
- If Confluence pages include walkthrough videos and the environment can access them, review them before writing tests. If videos are inaccessible, state that limitation in the final response and rely on the written documentation.
- You are a Playwright test generator.
- You are given a scenario and you need to generate a Playwright test.
- DO NOT generate test code based on the scenario alone.
- DO run steps one by one using the tools provided by Playwright MCP.
- To login to https://docker.dev.schoolbox.cloud/ the username is alaress and the password is alaress.
- Only after all steps are completed, emit a Playwright TypeScript test.
- If unable to find a link/button containing the specified text, also look for any links/buttons containing the text as a title attribute.
- If Playwright MCP is not able to execute the steps given, stop running after 15 seconds, provide explanation of error, and DO NOT create/update tests.
- Execute the test file and iterate until the test passes.
- To run a test use `DOCKER=1 npx playwright test dummy.spec.ts` where dummy is the name of the test file created.
- ALWAYS use the `DOCKER=1` environment variable when running Playwright.
- If running outside the Melbourne timezone and time inputs behave incorrectly, set `TZ='Australia/Melbourne'` when running Playwright.
- Run the specific new test first, then run the entire test file once any new tests have been added.
- Inspect failure artifacts under `test/playwright/_output/`.

# Playwright test file

- The test files should be written in TypeScript.
- If the test file does not already exist, save the generated test file in the folder `test/playwright/acceptance/dummy directory` where dummy is the name of the test.
- The file should be saved as `dummy.spec.ts` where dummy is the name of the test file.
- The test files `test/playwright/acceptance/curriculum/selector/curriculumSelector.spec.ts`, `test/playwright/acceptance/curriculum/report/curriculumCoverageReport.spec.ts`, and `test/playwright/acceptance/pastoralCare/differentiationProfiles/manageDifferentiationProfiles.spec.ts` should be referenced to match structure and conventions.
- It should import `test/playwright/support/integrations.ts` and use the exported `test` and `expect` methods as required.
- Before each test use the existing `loginAsAdmin` function to login.
- A JSDoc should be added to the top of the file, containing a summary of the entire test file, a reminder to `1 Provision sales.tar.gz data` and `Execute DOCKER=1 npx playwright test dummy.spec.ts` where dummy is the test file name.
- As much as possible, each test should be completely standalone and not rely on previous tests for the test to pass successfully.
- Keep each `test.describe` under the 5 minute CI timeout. Split scenarios when needed.
- Use `tester.isGoingTo()` to document intent in longer flows.
- After a test has been created/updated, review the entire test file and look for any repetitions that can instead be put in the helper file.
- To impersonate a user, use the existing method `await tester.impersonateViaUserProfilePage("User");` where `User` is the user to impersonate.
- When choosing selectors, ALWAYS use the `data-test` attribute if one is on the element.
- When using data-test attributes, they should be written like `await page.getByTestId("confirm-dialog-confirm-button").click();` not `await page.locator('[data-test="confirm-dialog-confirm-button"]').click();`.
- Whenever possible, data-test attributes should be added to the relevant element when not already referenced by a data-test attribute, and the new data-test attribute should be used in the test instead of other selectors.
- Name new `data-test` values uniquely, using a feature/field pattern such as `profile-editor-name-input`.
- Avoid fragile CSS selectors unless there is a clear reason.
- Wrap tests in `try/finally` with any cleanup occurring within `finally`.
- Avoid adding cleanup in `afterAll`; instead cleanup within each test's `finally` block.
- When a new test is added, test it individually before running all tests in the file again.

# Playwright helper files

- When it is likely a test will do an action or group of actions more than once, that action should be split out into a helper file.
- Helper files are stored in `test/playwright/support/page/dummy/` where dummy is the name of the test.
- The files `test/playwright/support/page/curriculum/selector/CurriculumSelector.ts`, `test/playwright/support/page/curriculum/report/CurriculumCoverageReport.ts`, and `test/playwright/support/page/pastoralCare/ManageDifferentiationProfiles.ts` should be referenced to match structure and conventions.
- The file should be a class.
- Before adding another method, ensure there is not already an existing method for the same action, or a method that can have a parameter provided if this will not make the method too complicated.

# Coverage documentation

- Once the new tests are committed, update the Confluence Automation Tests coverage page with the list of tests added: https://schoolbox.atlassian.net/wiki/spaces/DEV/pages/2211939023/Automation+Tests+coverage
- Add the entry under the Acceptance Tests section for Playwright UI coverage.
- Include the feature/module name, relevant URL if applicable, spec file name, and a concise list of behaviors covered.
- Include any meaningful limitations, skipped cases, or dependencies.
- If Confluence cannot be edited from the environment, include the exact coverage entry text in the final response so the PR author can paste it into the page.
