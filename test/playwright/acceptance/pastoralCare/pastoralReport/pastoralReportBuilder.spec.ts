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
import PastoralProfile from "@schoolboxTest/playwright/support/page/pastoralCare/PastoralProfile";
import CreatePastoralRecord from "@schoolboxTest/playwright/support/page/pastoralCare/CreatePastoralRecord";
import ResourcesMyFilesPicker from "@schoolboxTest/playwright/support/page/resources/ResourcesMyFilesPicker";

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

            // Student by Type tab: sort by Name and check Sasha White is in row 2
            await pastoralReportDetails.clickTab("Student by Type");
            await pastoralReportDetails.sortColumnAndCheck("user", { trIndex: 2, tdIndex: 1 }, "White, Sasha");

            // Type by Month tab: sort by Oct 2017 and check the 4 records number is in row 9
            await pastoralReportDetails.clickTab("Type by Month");
            await pastoralReportDetails.sortColumnAndCheck("oct_2017", { trIndex: 9, tdIndex: 13 }, "4");

            // Type by Week tab: sort by Total Records and check the 1 record number is in row 8
            await pastoralReportDetails.clickTab("Type by Week");
            await pastoralReportDetails.sortColumnAndCheck("total", { trIndex: 8, tdIndex: 2 }, "1");

            // Student by Month tab: sort by Oct 2017 and check the last row (6) has number 3
            await pastoralReportDetails.clickTab("Student by Month");
            await pastoralReportDetails.sortColumnAndCheck("oct_2017", { trIndex: 6, tdIndex: 13 }, "3");

            // Student by Week tab: sort by week 8 (29 Oct - 4 Nov, 2017) and check Penelope Li is in row 3
            await pastoralReportDetails.clickTab("Student by Week");
            await pastoralReportDetails.sortColumnAndCheck("week_8", { trIndex: 3, tdIndex: 1 }, "Li, Penelope");

            await pastoralReportDetails.clickRecordsOrPointsRadioButton("points");

            // Student by Type tab: sort by Name and check Sasha White is in row 2
            await pastoralReportDetails.clickTab("Student by Type");
            await pastoralReportDetails.sortColumnAndCheck("user", { trIndex: 2, tdIndex: 1 }, "White, Sasha");

            // Type by Month tab: sort by Oct 2017 and check the 5 points number is in row 9 for the oct column
            await pastoralReportDetails.clickTab("Type by Month");
            await pastoralReportDetails.sortColumnAndCheck("oct_2017", { trIndex: 9, tdIndex: 13 }, "5");

            // Type by Week tab: sort by Total Points and check the 5 points number is in row 9 for the total column
            await pastoralReportDetails.clickTab("Type by Week");
            await pastoralReportDetails.sortColumnAndCheck("total", { trIndex: 9, tdIndex: 2 }, "5");

            // Student by Month tab: sort by Oct 2017 and check the last row (6) has number 6 for the oct column
            await pastoralReportDetails.clickTab("Student by Month");
            await pastoralReportDetails.sortColumnAndCheck("oct_2017", { trIndex: 6, tdIndex: 13 }, "6");

            // Student by Week tab: sort by week 8 (29 Oct - 4 Nov, 2017) and check Penelope Li is in row 3
            await pastoralReportDetails.clickTab("Student by Week");
            await pastoralReportDetails.sortColumnAndCheck("week_8", { trIndex: 3, tdIndex: 1 }, "Li, Penelope");

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

            tester.isGoingTo("check the list tab shows the records details properly: title, severiy, points");
            await pastoralReportDetails.clickTab("List");
            await pastoralReportDetails.checkRecordDetails(
                recordId,
                studentId,
                "Ms Jana Reed: Academic Achievement Award - Semester 2 ",
                "Low",
                " 5 Points",
            );

            tester.isGoingTo("check if the proper numbers of tags are presented in the report");
            await pastoralReportDetails.checkTagCount(2);

            tester.isGoingTo("Download the report and confirm the download is successful");
            await common.assertFileDownload("pastoral-report-download-button", `${reportNameCsv}.csv`);

            tester.isGoingTo("check the print button is presented in the report details page");
            const printIcon = page.locator(`button[title="Print Report Results"]`);
            await expect(printIcon).toBeVisible();

            tester.isGoingTo("clicking a record in the list tab redirects to the record details page");
            await pastoralReportDetails.clickRecordInListTab(recordId, studentId);
            await expect(page).toHaveURL(/.*\/pastoral\/student\/25\/record\/5$/);

            tester.isGoingTo("check each one of the tabs load the records data correctly on the modal");
            await pastoralCareReports.goto();
            await pastoralCareReports.openPastoralReport(reportTitle);
            await pastoralReportDetails.clickTab("Student by Type");
            await pastoralReportDetails.clickCell({ trIndex: 4, tdIndex: 2 }); // Jana Reed row number 4, click the total records column
            await pastoralReportDetails.checkModalRowRecordCount(3); // check the modal opened and has 3 records
            await pastoralReportDetails.closeModal();

            await pastoralReportDetails.clickTab("Type by Month");
            await pastoralReportDetails.clickCell({ trIndex: 2, tdIndex: 13 }); // Type by Month tab: Award/Achievement row(number 2) clicking the Total Records Oct 2017 column
            await pastoralReportDetails.checkModalRowRecordCount(4); // check the modal opened and has 4 records
            await pastoralReportDetails.closeModal();

            await pastoralReportDetails.clickTab("Type by Week");
            await pastoralReportDetails.clickCell({ trIndex: 2, tdIndex: 48 }); // Type by Week tab: Award/Achievement row(number 2) clicking the Academic 29-Oct-4Nov column
            await pastoralReportDetails.checkModalRowRecordCount(4); // check the modal opened and has 4 records
            await pastoralReportDetails.closeModal();

            await pastoralReportDetails.clickTab("Student by Month");
            await pastoralReportDetails.clickCell({ trIndex: 3, tdIndex: 13 }); // Student by Month tab: Penelope row(number 3) clicking Oct 2017 column
            await pastoralReportDetails.checkModalRowRecordCount(1); // check the modal opened and has 1 record
            await pastoralReportDetails.closeModal();

            await pastoralReportDetails.clickTab("Student by Week");
            await pastoralReportDetails.clickCell({ trIndex: 6, tdIndex: 2 }); // Student by Week tab: Sasha row(number 6) clicking the total records column
            await pastoralReportDetails.checkModalRowRecordCount(1); // check the modal opened and has 1 record
            await pastoralReportDetails.closeModal();

            tester.isGoingTo("check each one of the tabs load the records data correctly on the table");
            await pastoralReportDetails.clickTab("Student by Type");
            await pastoralReportDetails.checkRecordCount({ trIndex: 4, tdIndex: 2 }, 3); // check the Jana Reed row (number 4) has 3 records in the total column

            await pastoralReportDetails.clickTab("Type by Month");
            await pastoralReportDetails.checkRecordCount({ trIndex: 2, tdIndex: 2 }, 4); // check the Academic Achievement row(number 2) checking the Total Records

            await pastoralReportDetails.clickTab("Type by Week");
            await pastoralReportDetails.checkRecordCount({ trIndex: 7, tdIndex: 48 }, 1); // check the Discipline Uniform Infraction row(number 7) checking the 29-Oct-4Nov

            await pastoralReportDetails.clickTab("Student by Month");
            await pastoralReportDetails.checkRecordCount({ trIndex: 4, tdIndex: 13 }, 3); // check the Jana Reed row(number 4) checking the Oct column

            await pastoralReportDetails.clickTab("Student by Week");
            await pastoralReportDetails.checkRecordCount({ trIndex: 2, tdIndex: 2 }, 1); // check the Mario Izzo row(number 2) checking the total records column

            tester.isGoingTo("check each one of the tabs load the points data correctly on the table");

            await pastoralReportDetails.clickTab("Student by Type");
            await pastoralReportDetails.clickRecordsOrPointsRadioButton("points");
            await pastoralReportDetails.checkRecordCount({ trIndex: 4, tdIndex: 2 }, 6); // check the Jana Reed row (number 4) - the Total Points column

            await pastoralReportDetails.clickTab("Type by Month");
            await pastoralReportDetails.checkRecordCount({ trIndex: 2, tdIndex: 2 }, 5); // check the Academic Achievement row(number 2) - the Total Points column

            await pastoralReportDetails.clickTab("Type by Week");
            await pastoralReportDetails.checkRecordCount({ trIndex: 2, tdIndex: 48 }, 5); // check the  Academic Achievement row(number 2) checking checking the 29-Oct-4Nov

            await pastoralReportDetails.clickTab("Student by Month");
            await pastoralReportDetails.checkRecordCount({ trIndex: 4, tdIndex: 13 }, 6); // check the Jana Reed row(number 4) checking the Oct column

            await pastoralReportDetails.clickTab("Student by Week");
            await pastoralReportDetails.checkRecordCount({ trIndex: 2, tdIndex: 48 }, 3); // check the Mario Izzo row(number 2) checking checking the 29-Oct-4Nov column

            tester.isGoingTo("check each one of the tabs load the points data correctly on the modal");
            await pastoralReportDetails.clickTab("Student by Type");
            await pastoralReportDetails.clickCell({ trIndex: 4, tdIndex: 2 }); // Jana Reed row (number 4) clicking the total points column
            await pastoralReportDetails.checkModalRowRecordCount(2); // check the modal opened and has 2 records
            await pastoralReportDetails.closeModal();

            await pastoralReportDetails.clickTab("Type by Month");
            await pastoralReportDetails.clickCell({ trIndex: 2, tdIndex: 13 }); // Academic row (number 2) clicking the Oct 2017 column
            await pastoralReportDetails.checkModalRowRecordCount(1); // check the modal opened and has 1 record
            await pastoralReportDetails.closeModal();

            await pastoralReportDetails.clickTab("Type by Week");
            await pastoralReportDetails.clickCell({ trIndex: 2, tdIndex: 48 }); // Award/Achievement row(number 2) clicking the Academic 29-Oct-4Nov column
            await pastoralReportDetails.checkModalRowRecordCount(1); // check the modal opened and has 1 record
            await pastoralReportDetails.closeModal();

            await pastoralReportDetails.clickTab("Student by Month");
            await pastoralReportDetails.clickCell({ trIndex: 2, tdIndex: 13 }); // Mario row (number 2) clicking Oct 2017 column
            await pastoralReportDetails.checkModalRowRecordCount(1); // check the modal opened and has 1 record
            await pastoralReportDetails.closeModal();

            await pastoralReportDetails.clickTab("Student by Week");
            await pastoralReportDetails.clickCell({ trIndex: 2, tdIndex: 2 }); // Mario  row (number 2) clicking the total points column
            await pastoralReportDetails.checkModalRowRecordCount(1); // check the modal opened and has 1 record
            await pastoralReportDetails.closeModal();
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

            tester.isGoingTo("check the types and dates are properly presented in the report header");
            await pastoralReportDetails.clickTab("Student by Type");
            await pastoralReportDetails.checkHeaderCount(7);
            await pastoralReportDetails.checkHeaderPresented(["1", "2", "7", "6", "5"]); //  academic and discipline subtypes are presented
            await pastoralReportDetails.checkHeaderNotPresented(["3", "4"]); // medical and bullying are not presented

            await pastoralReportDetails.clickTab("Type by Month");
            await pastoralReportDetails.checkHeaderCount(3);
            await pastoralReportDetails.checkDateHeaderPresented(["oct_2017"]); // only oct 2017 is presented
            await pastoralReportDetails.checkDateHeaderNotPresented(["sep_2017", "nov_2017"]); // sept and nov 2017 are not presented

            await pastoralReportDetails.clickTab("Type by Week");
            await pastoralReportDetails.checkHeaderCount(7);
            await pastoralReportDetails.checkDateHeaderPresented(["week_4", "week_3", "week_2", "week_1", "week_0"]); // the 5 weeks in October are presented
            await pastoralReportDetails.checkDateHeaderNotPresented(["week_5"]); // the week in November is not presented

            await pastoralReportDetails.clickTab("Student by Month");
            await pastoralReportDetails.checkHeaderCount(3);
            await pastoralReportDetails.checkDateHeaderPresented(["oct_2017"]); // only oct 2017 is presented
            await pastoralReportDetails.checkDateHeaderNotPresented(["sep_2017", "nov_2017"]); // sept and nov 2017 are not presented

            await pastoralReportDetails.clickTab("Student by Week");
            await pastoralReportDetails.checkHeaderCount(7);
            await pastoralReportDetails.checkDateHeaderPresented(["week_4", "week_3", "week_2", "week_1", "week_0"]); // the 5 weeks in October are presented
            await pastoralReportDetails.checkDateHeaderNotPresented(["week_5"]); // the week in November is not presented
        } finally {
            tester.isGoingTo("Clean up: delete the pastoral report");
            await db.dontHaveInDatabase("pastoral_report", { name: reportTitle, deleted_at: null });
        }
    });

    test("check the report builder List tab and modal shows the comments and attachments", async ({
        page,
        tester,
        db,
    }) => {
        try {
            const createPastoralReport = new CreatePastoralReport(page);
            const pastoralCareReports = new PastoralCareReports(page);
            const pastoralReportDetails = new PastoralReportDetails(page);
            const pastoralProfile = new PastoralProfile(page, tester);
            const createPastoralRecord = new CreatePastoralRecord(page);
            const resourcesMyFilesPicker = new ResourcesMyFilesPicker(tester, page);
            const recordId = 2; // Ms Jana Reed: Cyber Bullying
            const studentId = 25; // Jana Reed
            const commentText = "This is a general comment";
            const bodyPublic = "<p>This is an automation pastoral record public body</p>";

            tester.isGoingTo("update a pastoral record to have description, comments and attachments");
            await db.updateInDatabase("pastoral", { body_public: bodyPublic }, { id: recordId });
            await pastoralProfile.goto(studentId); // Jana Reed
            await pastoralProfile.clickPastoralRecord(studentId, recordId);
            await tester.common.clickAddComment();
            await tester.fillCKEditorById("comment-public", commentText);
            await pastoralProfile.clickPostComment();
            await pastoralProfile.clickModifyIcon(studentId, recordId);
            await page.locator("#public-zone").click();
            await resourcesMyFilesPicker.takePictureAndSave("PastoralRecord");
            await createPastoralRecord.clickSave();

            tester.isGoingTo(
                "navigate to create pastoral report page, fill in the report title, date range and save the report",
            );
            await createPastoralReport.createPastoralReport(reportTitle, "2017-01-01 00:00", "2017-12-31 23:59");

            tester.isGoingTo("check the list tab shows the comments and attachments");
            await pastoralCareReports.goto();
            await pastoralCareReports.openPastoralReport(reportTitle);
            await pastoralReportDetails.checkAttachmentPresented();
            await pastoralReportDetails.checkPublicBodyPresented("This is an automation pastoral record public body");
            await pastoralReportDetails.checkPrivateBodyNotPresented(
                "Jana and Mario have been using Facebook to bully Louisa Smith.",
            );
            await pastoralReportDetails.checkCommentPresented(commentText);
            await pastoralReportDetails.checkCommentNotPresented(
                "Parents have been contacted. Both students have been given a Saturday detention.",
            ); // this is the comment in the confidential panel, it should not be shown in the list tab

            tester.isGoingTo(
                "check the Student by Type tab and check if the modal shows the title, description, comments, severity, tags",
            );
            await pastoralReportDetails.clickTab("Student by Type");
            await pastoralReportDetails.clickCell({ trIndex: 4, tdIndex: 2 }); // Jana Reed row (number 4) clicking the total points column
            await pastoralReportDetails.checkModalRecordDetails(
                recordId,
                studentId,
                "Ms Jana Reed: Cyber Bullying ",
                "Critical",
                " 1 Point",
            );
            await pastoralReportDetails.checkTagCount(1);
            await pastoralReportDetails.checkCommentPresented(commentText);
            await pastoralReportDetails.checkCommentNotPresented(
                "Parents have been contacted. Both students have been given a Saturday detention.",
            ); // this is the comment in the confidential panel, it should not be shown in the modal as well
            await pastoralReportDetails.checkAttachmentPresented();
            await pastoralReportDetails.checkModalPublicBodyPresented(
                "This is an automation pastoral record public body",
            );
            await pastoralReportDetails.checkModalPrivateBodyNotPresented(
                "Jana and Mario have been using Facebook to bully Louisa Smith.",
            ); // this is the description in the confidential panel, it should not be shown in the modal as well
            await pastoralReportDetails.clickRecordInListTab(recordId, studentId);
            await expect(page).toHaveURL(new RegExp(`.*/pastoral/student/${studentId}/record/${recordId}$`));
        } finally {
            tester.isGoingTo("Clean up: delete the pastoral report and undo the pastoral record changes");
            await db.dontHaveInDatabase("pastoral_report", { name: reportTitle, deleted_at: null });
            await db.updateInDatabase("pastoral", { body_public: " " }, { id: 2 });
            await db.dontHaveInDatabase("pastoral_comment", { pastoral_id: 2, owner_id: 22, deleted_at: null });
            await db.dontHaveInDatabase("pastoral_attachment", { pastoral_id: 2, deleted_at: null });
        }
    });

    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("update the pastoral points as per provisioned data");
        await db.updateInDatabase("pastoral", { points: null }, { id: 2 });
        await db.updateInDatabase("pastoral", { points: null }, { id: 3 });
        await db.updateInDatabase("pastoral", { points: null }, { id: 4 });
        await db.updateInDatabase("pastoral", { points: null }, { id: 5 });
    });
});
