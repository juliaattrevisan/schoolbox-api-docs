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
import ManageCurriculum from "@schoolboxTest/playwright/support/page/curriculum/manage/ManageCurriculum";
import DisableNodes from "@schoolboxTest/playwright/support/page/curriculum/manage/action/DisableNodes";
import Common from "@schoolboxTest/playwright/support/page/Common";

test.describe("Curriculum Coverage Report Tests", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("Generate a report", async ({ tester, page }) => {
        const coverageReport = new CurriculumCoverageReport(page);
        const searchSelect = new SearchSelect(page);
        const searchSelectModal = new SearchSelectModal(page);
        const curriculumSelector = new CurriculumSelector(page);
        const common = new Common(page);

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

        // link for reference: https://vagrant.dev.schoolbox.cloud/curriculum/reports/coverage?courseId=6%2C2%2C14&nodeId=302%2C304%2C1%2C135

        tester.isGoingTo("Try and generate a report again");
        await coverageReport.submit(); // Generate the report

        tester.isGoingTo("Confirm loading spinner is visible");
        await coverageReport.expectLoading();

        tester.isGoingTo("Confirm English > Year 9 has total usage of 12");
        await expect(coverageReport.getYear9TotalUsageTableCell()).toHaveText("12");

        tester.isGoingTo("Reload the page and ensure the report still persists");
        await page.reload();

        tester.isGoingTo("Confirm loading spinner is visible");
        await coverageReport.expectLoading();

        tester.isGoingTo("Confirm English > Year 9 has total usage of 12");
        await expect(coverageReport.getYear9TotalUsageTableCell()).toHaveText("12");

        tester.isGoingTo("Select load more curriculum and confirm Years 9 and 10 French is now visible");
        await expect(page.locator("table tbody")).not.toContainText("Years 9 and 10 French");
        await page.getByTestId("cc-report-load-more-button").click(); // Select load more curriculum
        await expect(page.locator("table tbody")).toContainText("Years 9 and 10 French");

        tester.isGoingTo("Export the report and confirm the download is successful");
        await page.getByTestId("cc-report-filters-export-button").click(); // Select Export as CSV
        await common.assertFileDownload("cc-report-filters-export-button-confirm", "curriculum-coverage-report.csv");

        tester.isGoingTo("Uncheck Unit filter and confirm Year 9 Curriculum goes from 12 to 10");
        await expect(coverageReport.getYear9TotalUsageTableCell()).toHaveText("12");
        await coverageReport.clickUnitFilter();
        await expect(coverageReport.getYear9TotalUsageTableCell()).toHaveText("10");

        tester.isGoingTo("Uncheck Learning Activities filter and confirm Year 9 Curriculum goes from 10 to 3");
        await coverageReport.clickActivityFilter();
        await expect(coverageReport.getYear9TotalUsageTableCell()).toHaveText("3");

        tester.isGoingTo(
            "Uncheck Course filter and confirm Year 9 Curriculum goes from 3 to blank (as it's 0 and doesn't have a code)",
        );
        await coverageReport.clickCourseFilter();
        await expect(coverageReport.getYear9TotalUsageTableCell()).toHaveText("");

        tester.isGoingTo("Check all filters back on and confirm Year 9 Curriculum goes from 0 to 12");
        await coverageReport.clickUnitFilter();
        await coverageReport.clickActivityFilter();
        await coverageReport.clickCourseFilter();
        await expect(coverageReport.getYear9TotalUsageTableCell()).toHaveText("12");

        tester.isGoingTo("Expand out to English > Year 9 > Language > Text structure and organisation > ACELA1553");
        await coverageReport.navigateTree([136, 138, 148, 149]);

        tester.isGoingTo(
            "Select ACELA1553 / Romeo & Juliet and confirm “Present a scene of Romeo and Juliet” is visible in the pop out",
        );
        await coverageReport.acela1553RomeoTableCell().click(); // Select table cell
        await expect(page.getByTestId("cc-usage-pop-out")).toContainText("Present a scene of Romeo and Juliet");

        tester.isGoingTo("Uncheck activity filter again and confirm ACELA1553 / Romeo & Juliet 0 value is now hidden");
        await coverageReport.clickActivityFilter();
        await expect(coverageReport.acela1553RomeoTableCell()).not.toBeVisible();

        tester.isGoingTo("Select Only show unused curriculum and confirm open/close toggles have been hidden");
        await page.getByTestId("cc-report-filters-gaps-toggle").click(); // Select Only show unused curriculum
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

    test("Check Show disabled outcomes toggle", async ({ tester, page }) => {
        const manageCurriculum = new ManageCurriculum(page, tester);
        const disableNodes = new DisableNodes(page, tester);
        const coverageReport = new CurriculumCoverageReport(page);

        await manageCurriculum.goto();

        tester.isGoingTo("disable Year 4 History");

        // Check Year 4
        await manageCurriculum.checkNode("Year 4 History");

        // Click Disable button
        await disableNodes.clickDisableButton();

        tester.isGoingTo("Ensure the confirmation modal appears");
        await disableNodes.waitForConfirmModal();

        tester.isGoingTo("Confirm the disable action");
        await disableNodes.clickConfirmButton();

        tester.isGoingTo("Ensure the toast message is shown");
        await expect(page.getByTestId("toast")).toContainText("Outcome successfully disabled");

        tester.isGoingTo("Visit the curriculum coverage report page with disabled nodes preloaded");
        await coverageReport.gotoDisabledTest();

        tester.isGoingTo("Confirm the disabled node is visible");
        await expect(coverageReport.getTable()).toContainText("Year 4 History");

        tester.isGoingTo("Select the Show disabled outcomes toggle");
        await coverageReport.clickShowDisabledToggle();

        tester.isGoingTo("Confirm the disabled node is no longer visible");
        await expect(coverageReport.getTable()).not.toContainText("Year 4 History");

        tester.isGoingTo("Select the Show disabled outcomes toggle again");
        await coverageReport.clickShowDisabledToggle();

        tester.isGoingTo("Confirm the disabled node is visible");
        await expect(coverageReport.getTable()).toContainText("Year 4 History");
    });

    /**
     * Soft deleting data to not impact the tests
     */
    test.afterAll(async ({ baseTester, db }) => {
        // Soft-delete data to not interfere with other tests
        baseTester.isGoingTo("update enabled on the Year 4 History so it does not impact next suite of tests");
        // reset the enabled status of Year 4 History
        await db.updateInDatabase("curriculum_node", { enabled: 1 }, { "name like": "Year 4 History" });
    });
});
