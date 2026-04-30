---
name: create-playwright-tests
description: Use when the user asks to create or update Playwright tests based on a scenario. Generate tests iteratively by running steps with Playwright MCP, and only emit the final test code after all steps have been completed successfully.
---

# General

- You are a playwright test generator.
- You are given a scenario and you need to generate a playwright test.
- DO NOT generate test code based on the scenario alone.
- DO run steps one by one using the tools provided by Playwright MCP.
- To login to https://docker.dev.schoolbox.cloud/ the username is alaress and the password is alaress
- Only after all steps are completed, emit a Playwright TypeScript test.
- If unable to find a link/button containing the specified text, also look for any links/buttons containing the text as a title attribute.
- If Playwright MCP is not able to execute the steps given, stop running after 15 seconds, provide explanation of error, and DO NOT create/update tests.
- Execute the test file and iterate until the test passes.
- To run a test use `DOCKER=1 npx playwright test dummy.spec.ts` where dummy is the name of the test file created.
- When choosing selectors ALWAYS use the data-test attribute if on the element.
- ALWAYS use the DOCKER=1 environment variable when running playwright
- Run the entire test file once any new tests have been added

# Playwright test file

- The test files should be written in TypeScript.
- If the test file does not already exist save generated test file in the folder `test/playwright/acceptance/dummy directory` where dummy is the name of the test.
- The file should be saved as `dummy.spec.ts` where dummy is the name of the test file.
- The test files `test/playwright/acceptance/curriculum/selector/curriculumSelector.spec.ts`, `test/playwright/acceptance/curriculum/report/curriculumCoverageReport.spec.ts` and `test/playwright/acceptance/pastoralCare/differentiationProfiles/manageDifferentiationProfiles.spec.ts` should be referenced to match structure and conventions.
- It should import test/playwright/support/integrations.ts and use the exported test and expect methods as required.
- Before each test use the existing `loginAsAdmin` function to login.
- A JSDoc should be added to the top of the file, containing a summary of the entire test file, a reminder to `1 Provision sales.tar.gz data` and `Execute DOCKER=1 npx playwright test dummy.spec.ts` where dummy is the test file name.
- As much as possible, each test should be completely stand alone and not rely on previous tests for the test to pass successfully.
- After a test has been created/updated, review the entire test file and look for any repetitions that can instead by put in the helper file.
- To impersonate a user, use the existing method `await tester.impersonateViaUserProfilePage("User");` where `User` is the user to impersonate.
- When using data-test attributes, they should be written like for example `await page.getByTestId("confirm-dialog-confirm-button").click();` not `await page.locator('[data-test="confirm-dialog-confirm-button"]').click();`.
- Whenever possible, data-test attributes should be added to the relevant element when not already referenced by a data-test attribute, and the new data-test attribute should be used in the test instead of other selectors.
- Wrap tests in `try/finally` with any clean up occurring within `finally`.
- Avoid adding cleanup in `afterAll`, instead cleanup within each tests `finally` block.
- When a new test is added, test individually before running all tests in the file again.

# Playwright helper files

- When it's like a test will do an action or group of actions more than once, that action should be split out into a helper file.
- Helper files are stored in `test/playwright/support/page/dummy/` where dummy is the name of the test.
- The files `test/playwright/support/page/curriculum/selector/CurriculumSelector.ts`, `test/playwright/support/page/curriculum/report/CurriculumCoverageReport.ts` and `test/playwright/support/page/pastoralCare/ManageDifferentiationProfiles.ts` should be referenced to match structure and conventions.
- The file should be a class.
- Before adding another method in, ensure there isn't already an existing method for the same action, or a method that can have a parameter provided this wont make the method too complicated.
