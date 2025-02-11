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

        tester.isGoingTo("Within Select Courses: Choose Year 9 English, Year 9 French and 20th Century History");
        await page.getByTestId("search-select-button-courses").click(); // Open the select courses modal
        await page.getByTestId("ss-results-item-label-6").click(); // Select Year 9 English
        await page.getByTestId("ss-results-item-label-2").click(); // Select Year 9 French
        await page.getByTestId("ss-results-item-label-14").click(); // Select 20th Century History
        await page.getByTestId("ssm-footer-save-button").click(); // Select Select, closing the modal

        tester.isGoingTo("Check that those courses are selected");
        await expect(page.getByTestId("ss-selected")).toContainText("Year 9 English");
        await expect(page.getByTestId("ss-selected")).toContainText("Year 9 French");
        await expect(page.getByTestId("ss-selected")).toContainText("20th Century History");

        tester.isGoingTo("Try and generate a report, confirming that Curriculum is also required");
        await coverageReport.submit(); // Generate the report
        await expect(page.getByTestId("search-select-curriculum")).toContainText(
            "Please select at least one curriculum outcome",
        );

        tester.isGoingTo(
            "Within Select Curriculum: Choose Year 12 French, Years 9 and 10 French, Year 9 History and English ",
        );
        await page.getByTestId("search-select-button-curriculum").click(); // Open the select courses modal
        await page.getByTestId("cs-modal-node-checkbox-label-302").click(); // Select Year 12 French
        await page.getByTestId("cs-modal-node-checkbox-label-304").click(); // Select Years 9 and 10 French
        await page.getByTestId("cs-modal-node-checkbox-label-1").click(); // Select Year 9 History
        await page.getByTestId("cs-modal-node-checkbox-label-135").click(); // Select English
        await page.getByTestId("cs-modal-footer-save-button").click(); // Select Select, closing the modal

        tester.isGoingTo("Try and generate a report again");
        await coverageReport.submit(); // Generate the report
    });
});
