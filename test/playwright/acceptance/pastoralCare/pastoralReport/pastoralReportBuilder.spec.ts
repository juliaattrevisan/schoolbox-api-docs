/**
 * Tests for pastoral care reports
 *
 * Prerequisites:
 * 1. Provision sales.tar.gz data
 *
 * Execute: npx playwright test pastoralReportBuilder.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CreatePastoralReport from "@schoolboxTest/playwright/support/page/pastoralCare/pastoralReportBuilder/CreatePastoralReport";
import PastoralCareReports from "@schoolboxTest/playwright/support/page/pastoralCare/pastoralReportBuilder/PastoralCareReports";
import PastoralReportDetails from "@schoolboxTest/playwright/support/page/pastoralCare/pastoralReportBuilder/PastoralReportDetails";
import Common from "@schoolboxTest/playwright/support/page/Common";

test.describe("Pastoral Report Builder", () => {
    const reportTitle: string = "Pastoral Report Automation Test";
    const reportNameCsv: string = "report-pastoral_report_automation_test";

    /**
     * update some pastoral records in the database so that they have points
     */
    test.beforeAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("update points for the pastoral records to get data for the report");
        await db.updateInDatabase("pastoral", { points: "1" }, { id: 2 });
        await db.updateInDatabase("pastoral", { points: "3" }, { id: 3 });
        await db.updateInDatabase("pastoral", { points: "-3" }, { id: 4 });
        await db.updateInDatabase("pastoral", { points: "5" }, { id: 5 });
    });

    /**
     * Preparations
     *
     * get logged in as admin and impersonate a teacher
     */
    test.beforeEach(async ({ tester }) => {
        await tester.impersonateTeacher();
    });

    /**
     * Create a pastoral report with all frequency tables selected,
     * check all the tables are presented in the report,
     * modify the report to exclude some frequency tables,
     * check only the selected tables are presented in the report
     * check the columns can be sorted properly
     */
    test("create a pastoral report and check whether the frequency tables are presented or not, check the columns can be sorted and proper student, date and types are presented", async ({
        page,
        tester,
        db,
    }) => {
        try {
            const createPastoralReport = new CreatePastoralReport(page);
            const pastoralCareReports = new PastoralCareReports(page);
            const pastoralReportDetails = new PastoralReportDetails(page);

            tester.isGoingTo(
                "navigate to create pastoral report page, fill in the report title, date range and save the report",
            );
            await createPastoralReport.createPastoralReport(reportTitle, "2017-01-01 00:00", "2017-12-31 23:59");

            tester.isGoingTo("check the pastoral report is created and the tabs are presented");
            await pastoralCareReports.goto();
            const reportsSection = page.locator("#studentsList");
            await expect(reportsSection.getByText(reportTitle)).toBeVisible();

            tester.isGoingTo("open the created report and check all tabs are presented");
            await pastoralCareReports.openPastoralReport(reportTitle);
            const pastoralId = (await db.grabFromDatabase("pastoral_report", "id", {
                "name like": reportTitle,
                deleted_at: null,
            })) as number;
            await pastoralReportDetails.checkTabsArePresented([
                "List",
                "Student by Type",
                "Type by Month",
                "Type by Week",
                "Student by Month",
                "Student by Week",
            ]);

            tester.isGoingTo("sort the columns and check it works properly");

            await pastoralReportDetails.sortColumnAndCheck(
                // Student by Type tab: sort by Name and check Sasha White the first row
                // Type by Month tab: sort by Oct 2017 and check the 4 records number is in the row 9
                // Type by Week tab: sort by Total Records and check the 1 record number is in the row 8
                // Student by Month tab: sort by Oct 2017 and check the last row has number 3
                // Student by Week tab: sort by 29 Oct - 4 Nov, 2017  and check Penelope Li is in the row 4

                ["Student by Type", "Type by Month", "Type by Week", "Student by Month", "Student by Week"],
                ["user", "oct_2017", "total", "oct_2017", "week_8"],
                ["user-Sasha", "oct_2017-4", "total-1", "oct_2017-3", "user-Penelope"],
                [2, 9, 8, 6, 3], // row numbers
                ["White, Sasha", "4", "1", "3", "Li, Penelope"],
            );

            await pastoralReportDetails.clickRecordsOrPointsRadioButton("points");

            await pastoralReportDetails.sortColumnAndCheck(
                // Student by Type tab: sort by Name and check Sasha White the first row
                // Type by Month tab: sort by Oct 2017 and check the 5 points number is in the row 9 for the oct column
                // Type by Week tab: sort by Total Points and check the 5 points number is in the row 9 for the total column
                // Student by Month tab: sort by Oct 2017 and check the last row has number 6 for the oct column
                // Student by Week tab: sort by 29 Oct - 4 Nov, 2017  and check Penelope Li is in the row 3

                ["Student by Type", "Type by Month", "Type by Week", "Student by Month", "Student by Week"],
                ["user", "oct_2017", "total", "oct_2017", "week_8"],
                ["user-Sasha", "oct_2017-5", "total-5", "oct_2017-6", "user-Penelope"],
                [2, 9, 9, 6, 3], // row numbers
                ["White, Sasha", "5", "5", "6", "Li, Penelope"],
            );

            tester.isGoingTo(
                "modify the created report to exclude frequency tables and check that only the ones selected are presented in the report",
            );
            await createPastoralReport.gotoModifyPage(pastoralId);
            await createPastoralReport.selectFrequencyTables("studentByType");
            await createPastoralReport.selectFrequencyTables("studentByMonth");
            await createPastoralReport.clickSaveButton();
            await pastoralCareReports.goto();
            await pastoralCareReports.openPastoralReport(reportTitle);
            await pastoralReportDetails.checkTabsAreNotPresented(["Student by Month", "Student by Type"]);
            await pastoralReportDetails.checkTabsArePresented([
                "List",
                "Type by Month",
                "Type by Week",
                "Student by Week",
            ]);
        } finally {
            tester.isGoingTo("Clean up: delete the pastoral report");
            await db.dontHaveInDatabase("pastoral_report", { name: reportTitle, deleted_at: null });
        }
    });

    /**
     * Create a pastoral report with all frequency tables selected using a fixed date range,
     * click each one of the tabs to ensure they load the records and points correctly via table and modal
     * check the print button is presented and able to export the report
     */
    test("check the tabs load records and points correctly: count number of records and points, check record details, check print button is presented, check download works", async ({
        page,
        tester,
        db,
    }) => {
        try {
            const createPastoralReport = new CreatePastoralReport(page);
            const pastoralCareReports = new PastoralCareReports(page);
            const pastoralReportDetails = new PastoralReportDetails(page);
            const common = new Common(page);
            const recordId = 5; // Ms Jana Reed: Academic Achievement Award - Semester 2
            const studentId = 25; // Jana Reed

            tester.isGoingTo("create a pastoral report and check the list tab shows the records properly");
            await createPastoralReport.createPastoralReport(reportTitle, "2017-01-01 00:00", "2017-12-31 23:59");
            await pastoralCareReports.goto();
            await pastoralCareReports.openPastoralReport(reportTitle);
            await pastoralReportDetails.checkListTabRecordCount(7);
            await pastoralReportDetails.clickTabsAndCheckRowCounts(
                ["Student by Type", "Type by Month", "Type by Week", "Student by Month", "Student by Week"],
                [5, 8, 8, 5, 5],
            );

            tester.isGoingTo("check the list tab shows the records details properly");
            await pastoralReportDetails.clickTab("List");
            await pastoralReportDetails.checkRecordDetails(
                recordId,
                studentId,
                "Ms Jana Reed: Academic Achievement Award - Semester 2 ",
                "Low",
                " 5 Points",
            );

            tester.isGoingTo("Download the report and confirm the download is successful");
            await common.assertFileDownload("pastoral-report-download-button", `${reportNameCsv}.csv`);

            tester.isGoingTo("check the print button is presented in the report details page");
            const printIcon = page.locator(`button[title="Print Report Results"]`);
            await expect(printIcon).toBeVisible();

            tester.isGoingTo("clicking a record in the list tab redirects to the record details page");
            await pastoralReportDetails.clickRecordInListTab(recordId, studentId);
            await expect(page).toHaveURL(/.*\/pastoral\/student\/25\/record\/5$/);

            tester.isGoingTo("check each one of the tabs load the records data correctly");
            await pastoralCareReports.goto();
            await pastoralCareReports.openPastoralReport(reportTitle);

            await pastoralReportDetails.clickTabAndCheckModalRecordCounts(
                // Student by Type tab: Jana Reed row(number 4) clicking the total records column
                // Type by Month tab: Award/Achievement row(number 2) clicking the Total Records Oct 2017 column
                // Type by Week tab: Award/Achievement row(number 2) clicking the Academic 29-Oct-4Nov column
                // Student by Month tab: Penelope row(number 3) clicking Oct 2017 column
                // Student by Week tab: Sasha row(number 6) clicking the total records column
                ["Student by Type", "Type by Month", "Type by Week", "Student by Month", "Student by Week"],
                [4, 2, 2, 3, 6], // row numbers
                ["total-3", "oct_2017-4", "week_8-4", "oct_2017-1", "total-1"],
                [3, 4, 4, 1, 1], // expected counts
            );

            await pastoralReportDetails.checkRowCounts(
                // Student by Type tab: Jana Reed row(number 4) checking the total column number 3;
                // Type by Month tab: Academic Achievement row(number 2) checking the Total Records;
                // Type by Week tab: Discipline Uniform Infraction row(number 7) checking the 29-Oct-4Nov;
                // Student by Month tab: Jana Reed row(number 4) checking the Oct column;
                // Student by Week tab: Mario Izzo row(number 2) checking the total records column;
                ["Student by Type", "Type by Month", "Type by Week", "Student by Month", "Student by Week"],
                [4, 2, 7, 4, 2], // row numbers
                ["total-3", "total-4", "week_8-1", "oct_2017-3", "total-1"],
                [`3`, `4`, `1`, `3`, `1`], // expected counts
            );

            tester.isGoingTo("check each one of the tabs load the points data correctly");

            await pastoralReportDetails.clickTab("Student by Type");
            await pastoralReportDetails.clickRecordsOrPointsRadioButton("points");

            await pastoralReportDetails.checkRowCounts(
                // Student by Type tab: Jana Reed row(number 4) checking the total column;
                // Type by Month tab: Academic Achievement row(number 2) checking the Total Points;
                // Type by Week tab: Academic Achievement row(number 2) checking checking the 29-Oct-4Nov;
                // Student by Month tab: Jana Reed row(number 4) checking the Oct column;
                // Student by Week tab: Mario Izzo row(number 2) checking the 29-Oct-4Nov column;
                ["Student by Type", "Type by Month", "Type by Week", "Student by Month", "Student by Week"],
                [4, 2, 2, 4, 2], // row numbers
                ["total-6", "total-5", "week_8-5", "oct_2017-6", "total-3"],
                [`6`, `5`, `5`, `6`, `3`], // expected counts
            );

            await pastoralReportDetails.clickTabAndCheckModalRecordCounts(
                // Student by Type tab: Jana Reed row (number 4) clicking the total points column
                // Type by Month tab: Academic row (number 2) clicking the Oct 2017 column
                // Type by Week tab: Academic row (number 2) clicking the 29-Oct-4Nov column
                // Student by Month tab: Mario row (number 2) clicking Oct 2017 column
                // Student by Week tab: Mario  row (number 2) clicking the total points column
                ["Student by Type", "Type by Month", "Type by Week", "Student by Month", "Student by Week"],
                [4, 2, 2, 2, 2], // row numbers
                ["total-6", "oct_2017-5", "week_8-5", "oct_2017-3", "total-3"],
                [2, 1, 1, 1, 1], // expected counts
            );
        } finally {
            tester.isGoingTo("Clean up: delete the pastoral report");
            await db.dontHaveInDatabase("pastoral_report", { name: reportTitle, deleted_at: null });
        }
    });

    /**
     * Creates a pastoral report builder
     * check the dates and students are properly presented in the report
     */
    test("creates a report and check the dates and students are properly presented in the report", async ({
        page,
        tester,
        db,
    }) => {
        try {
            const createPastoralReport = new CreatePastoralReport(page);
            const pastoralCareReports = new PastoralCareReports(page);
            const pastoralReportDetails = new PastoralReportDetails(page);

            tester.isGoingTo(
                "navigate to create pastoral report page, fill in the report title, date range, records types Academic and Medical and save the report",
            );
            await createPastoralReport.createPastoralReport(
                reportTitle,
                "2017-10-01 00:00",
                "2017-10-31 23:59",
                [],
                ["1", "5"],
            );

            await pastoralCareReports.goto();
            await pastoralCareReports.openPastoralReport(reportTitle);

            tester.isGoingTo("check the dates and students are properly presented in the report header");
            await pastoralReportDetails.checkSortButtonsAndTypesDatesPresence(
                ["Student by Type", "Type by Month", "Type by Week", "Student by Month", "Student by Week"],
                [7, 3, 7, 3, 7], // number of headers presented: name + total + subtypes
                [["1", "2", "7", "6", "5"], [], [], [], []], // academic and discipline subtypes are presented
                [["3", "4"], [], [], [], []], // medical and bullying are not presented
                [
                    [],
                    ["oct_2017"],
                    ["week_4", "week_3", "week_2", "week_1", "week_0"],
                    ["oct_2017"],
                    ["week_4", "week_3", "week_2", "week_1", "week_0"],
                ],
                [[], ["nov_2017"], ["week_5"], ["sep_2017"], ["week_5"]],
            );
        } finally {
            tester.isGoingTo("Clean up: delete the pastoral report");
            await db.dontHaveInDatabase("pastoral_report", { name: reportTitle, deleted_at: null });
        }
    });
});
