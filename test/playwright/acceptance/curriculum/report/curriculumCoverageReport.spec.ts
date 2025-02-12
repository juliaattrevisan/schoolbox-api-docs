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

        tester.isGoingTo("Visit the curriculum coverage report page");
        await coverageReport.goto();

        tester.isGoingTo("Confirm the empty state is visible");
        await expect(page.getByTestId("cc-report-empty-state")).toBeVisible();

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

        tester.isGoingTo("Confirm loading spinner is visible");
        await expect(page.getByTestId("cc-report-loading")).toBeVisible();

        tester.isGoingTo("Confirm English > Year 9 has total usage of 12");
        await expect(page.locator("table tbody tr:nth-child(4) td:nth-child(2)")).toHaveText("12");

        tester.isGoingTo("Reload the page and ensure the report still persists");
        await page.reload();

        tester.isGoingTo("Confirm loading spinner is visible");
        await expect(page.getByTestId("cc-report-loading")).toBeVisible();

        tester.isGoingTo("Confirm English > Year 9 has total usage of 12");
        await expect(page.locator("table tbody tr:nth-child(4) td:nth-child(2)")).toHaveText("12");

        tester.isGoingTo("Select load more curriculum and confirm Years 9 and 10 French is now visible");
        await page.getByTestId("cc-report-load-more-button").click(); // Select load more curriculum
        await expect(page.locator("table tbody tr:nth-child(7) td:nth-child(1)")).toHaveText("Years 9 and 10 French");

        tester.isGoingTo("Export the report and confirm the download is successful");
        await page.getByTestId("cc-report-filters-export-button").click(); // Select Export as CSV
        const [download] = await Promise.all([
            page.waitForEvent("download"), // Wait for the file to be downloaded
            page.getByTestId("cc-report-filters-export-button-confirm").click(), // Click the download button
        ]);
        const fileName = download.suggestedFilename();
        expect(fileName).toBe("curriculum-coverage-report.csv"); // Confirm the file name

        tester.isGoingTo("Uncheck Unit filter and confirm Year 9 Curriculum goes from 12 to 10");
        await page.getByTestId("cc-report-filters-label-unit").click(); // Select Unit filter
        await expect(page.locator("table tbody tr:nth-child(4) td:nth-child(2)")).toHaveText("10");

        tester.isGoingTo("Expand out to English > Year 9 > Language > Text structure and organisation > ACELA1553");
        await page.getByTestId("cc-report-table-node-expand-button-136").click(); // Select Year 9
        await page.getByTestId("cc-report-table-node-expand-button-138").click(); // Select Language
        await page.getByTestId("cc-report-table-node-expand-button-148").click(); // Select Text structure and organisation
        await page.getByTestId("cc-report-table-node-expand-button-149").click(); // Select ACELA1553

        tester.isGoingTo(
            "Select ACELA1553 / Romeo & Juliet and confirm “Present a scene of Romeo and Juliet” is visible in the pop out",
        );
        await page.locator("table tbody tr:nth-child(10) td:nth-child(10)").click(); // Select table cell
        await expect(page.getByTestId("cc-usage-pop-out")).toContainText("Present a scene of Romeo and Juliet");

        tester.isGoingTo("Select Only show unused curriculum and confirm open/close toggles have been hidden");
        await page.getByTestId("cc-report-filters-gaps-switch").click(); // Select Only show unused curriculum
        await expect(page.locator("[data-test^='cc-report-table-node-expand-button-']")).toHaveCount(0); // Confirm no expand buttons

        tester.isGoingTo(
            "Scroll down to the bottom of the table, allowing time for all rows to become visible and confirm ACLFRC109 is the last row in the table",
        );
        await page.getByTestId("cc-report-table").hover(); // First hover over the scrollable table
        // await page.getByText("Footer text").scrollIntoViewIfNeeded();
        // const element = page.getByTestId("cc-report-table-node-title-button-307"); // Replace with the actual selector of the element
        // await element.scrollIntoViewIfNeeded(); // Scroll to ensure element is in view
        // await page.mouse.wheel(0, 5000); // Scroll to the bottom of the table
        // await page.waitForTimeout(1000);
        // await page.mouse.wheel(0, 10000); // Scroll to the bottom of the table
        // await page.waitForTimeout(1000);
        // await page.mouse.wheel(0, 15000); // Scroll to the bottom of the table
        // await page.waitForTimeout(1000);
        // await page.mouse.wheel(0, 20000); // Scroll to the bottom of the table
        // await page.waitForTimeout(1000);
        // await expect(page.getByTestId("cc-report-table-node-title-button-307")).toHaveText("ACLFRC109");
        // await page.getByTestId("cc-report-table").evaluate((element) => (element.scrollTop += 100));
        let scrollHeight = 0;
        scrollHeight = await page.getByTestId("cc-report-table").evaluate((element) => element.scrollHeight);
        tester.isGoingTo(`ScrollHeight: ${scrollHeight}`);
        await page.mouse.wheel(0, scrollHeight + 100); // Scroll to the bottom of the table
        scrollHeight = await page.getByTestId("cc-report-table").evaluate((element) => element.scrollHeight);
        tester.isGoingTo(`ScrollHeight: ${scrollHeight}`);
        await page.mouse.wheel(0, scrollHeight + 100); // Scroll to the bottom of the table
        scrollHeight = await page.getByTestId("cc-report-table").evaluate((element) => element.scrollHeight);
        tester.isGoingTo(`ScrollHeight: ${scrollHeight}`);
        await page.mouse.wheel(0, scrollHeight + 100); // Scroll to the bottom of the table
        scrollHeight = await page.getByTestId("cc-report-table").evaluate((element) => element.scrollHeight);
        tester.isGoingTo(`ScrollHeight: ${scrollHeight}`);
        await page.mouse.wheel(0, scrollHeight + 100); // Scroll to the bottom of the table
        scrollHeight = await page.getByTestId("cc-report-table").evaluate((element) => element.scrollHeight);
        tester.isGoingTo(`ScrollHeight: ${scrollHeight}`);
        // await page.mouse.wheel(0, scrollHeight); // Scroll to the bottom of the table
        // scrollHeight = await page.getByTestId("cc-report-table").evaluate((element) => element.scrollHeight);
        // await page.mouse.wheel(0, scrollHeight); // Scroll to the bottom of the table
        // scrollHeight = await page.getByTestId("cc-report-table").evaluate((element) => element.scrollHeight);
        // await page.mouse.wheel(0, scrollHeight); // Scroll to the bottom of the table
        // scrollHeight = await page.getByTestId("cc-report-table").evaluate((element) => element.scrollHeight);
        // await page.mouse.wheel(0, scrollHeight); // Scroll to the bottom of the table
        // scrollHeight = await page.getByTestId("cc-report-table").evaluate((element) => element.scrollHeight);
        // await page.mouse.wheel(0, scrollHeight); // Scroll to the bottom of the table
        // scrollHeight = await page.getByTestId("cc-report-table").evaluate((element) => element.scrollHeight);
        // await page.mouse.wheel(0, scrollHeight); // Scroll to the bottom of the table
        // scrollHeight = await page.getByTestId("cc-report-table").evaluate((element) => element.scrollHeight);
        // await page.mouse.wheel(0, scrollHeight); // Scroll to the bottom of the table
        // await expect(page.getByTestId("cc-report-table-node-title-button-307")).toHaveText("ACLFRC109");
    });
});
