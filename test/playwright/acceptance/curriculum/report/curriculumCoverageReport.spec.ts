/**
 * Test the core functionality of the curriculum coverage report page
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test curriculumCoverageReport.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CurriculumCoverageReport from "@schoolboxTest/playwright/support/page/curriculum/report/CurriculumCoverageReport";

test.describe("Curriculum Coverage Report Tests", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("Generate a report", async ({ tester, page, db }) => {
        const coverageReport = new CurriculumCoverageReport(page);

        tester.isGoingTo("visit the curriculum coverage report page");
        await coverageReport.goto();

        tester.isGoingTo("confirm the empty state is visible");
        await expect(page.getByTestId("cc-empty-state")).toBeVisible();
    });
});
