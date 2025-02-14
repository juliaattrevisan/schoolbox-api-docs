/**
 * Test the core functionality of the curriculum coverage report page
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test curriculumCoverageReport.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CurriculumCoverageReport from "@schoolboxTest/playwright/support/page/curriculum/report/CurriculumCoverageReport";
import SearchSelect from "@schoolboxTest/playwright/support/components/SearchSelect";
import SearchSelectModal from "@schoolboxTest/playwright/support/components/SearchSelectModal";
import CurriculumSelector from "@schoolboxTest/playwright/support/page/curriculum/selector/CurriculumSelector";

test.describe("Curriculum Coverage Report Tests", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("Generate a report", async ({ tester, page, db }) => {
        const coverageReport = new CurriculumCoverageReport(page);
        const searchSelect = new SearchSelect(page);
        const searchSelectModal = new SearchSelectModal(page);
        const curriculumSelector = new CurriculumSelector(page);

        tester.isGoingTo("Visit the curriculum coverage report page");
        await coverageReport.goto();

        tester.isGoingTo("Confirm the empty state is visible");
        await expect(page.getByTestId("cc-report-empty-state")).toBeVisible();

        tester.isGoingTo("Within Select Courses: Choose Year 9 English, Year 9 French and 20th Century History");
        await searchSelect.selectButton("courses");
        await searchSelectModal.selectResults([6, 2, 14]);
        await searchSelectModal.save();

        tester.isGoingTo("Check that those courses are selected");
        await searchSelect.expectItemSelected("Year 9 English");
        await searchSelect.expectItemSelected("Year 9 French");
        await searchSelect.expectItemSelected("20th Century History");

        tester.isGoingTo("Try and generate a report, confirming that Curriculum is also required");
        await coverageReport.submit(); // Generate the report
        await expect(page.getByTestId("search-select-curriculum")).toContainText(
            "Please select at least one curriculum outcome",
        );

        tester.isGoingTo(
            "Within Select Curriculum: Choose Year 12 French, Years 9 and 10 French, Year 9 History and English ",
        );
        await searchSelect.selectButton("curriculum"); // Open the select courses modal
        await curriculumSelector.browseTree([302, 304, 1, 135]);
        await curriculumSelector.saveModal(); // Select Select, closing the modal

        tester.isGoingTo("Try and generate a report again");
        await coverageReport.submit(); // Generate the report

        tester.isGoingTo("Confirm loading spinner is visible");
        await coverageReport.expectLoading();

        tester.isGoingTo("Confirm English > Year 9 has total usage of 12");
        await expect(page.locator("table tbody tr:nth-child(4) td:nth-child(2)")).toHaveText("12");

        tester.isGoingTo("Reload the page and ensure the report still persists");
        await page.reload();

        tester.isGoingTo("Confirm loading spinner is visible");
        await coverageReport.expectLoading();

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
            "Scroll down to the bottom of the table, allowing another set of nodes to be displayed and confirm ELBH281 is now visible",
        );
        await expect(page.getByTestId("cc-report-table-node-title-button-124")).toHaveCount(0); // Confirm node not visible until scrolled further
        const scrollHeight = await page.getByTestId("cc-report-table").evaluate((element) => element.scrollHeight);
        await page.getByTestId("cc-report-table").hover(); // First hover over the scrollable table
        await page.mouse.wheel(0, scrollHeight); // then scroll to the bottom of the table
        await expect(page.getByTestId("cc-report-table-node-title-button-124")).toHaveCount(1); // Confirm node now visible after scroll
    });
});
