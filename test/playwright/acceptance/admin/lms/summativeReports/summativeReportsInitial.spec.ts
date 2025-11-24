import { test } from "@schoolboxTest/playwright/support/integrations";
import CreateSummativeReport from "@schoolboxTest/playwright/support/page/admin/lms/summativeReports/CreateSummativeReport";
import SummativeList from "@schoolboxTest/playwright/support/page/admin/lms/summativeReports/SummativeReportList";
import path from "path";

/**
 * check the summative empty message is presented
 * check the summative report error message is presented: title, academic periods, student selection, curriculum progression title
 * create a summative report
 * modify the summative report
 * click the preview icon and ensure the title, student name, year level, school logo and introductory message are properly presented
 * duplicate a summative report
 * delete a summative report
 */

test.describe.serial("Admin LMS - Summative Reports Initial", () => {
    let summativeReportList: SummativeList;
    let createSummativeReport: CreateSummativeReport;

    const summativeReportTitle = "Summative Report Initial Test";
    const modifiedTitle = `${summativeReportTitle} - Modified`;
    const schoolLogo = path.join(__dirname, "../../../../support/file/baboon.png");

    test.beforeEach(async ({ tester, page }) => {
        summativeReportList = new SummativeList(tester, page);
        createSummativeReport = new CreateSummativeReport(tester, page);
        await tester.loginAsAdmin();
    });

    test("check the summative empty message and mandatory fields validation; create a summative report and preview it", async ({
        tester,
        page,
    }) => {
        tester.isGoingTo(
            "navigate to create summative report page and click save without filling any mandatory fields",
        );
        await summativeReportList.goto();
        await summativeReportList.checkEmptyStateIsPresented();
        await summativeReportList.clickCreateSummativeReportButton();
        await createSummativeReport.toggleSummaryInclusion();
        await createSummativeReport.toggleClassInclusion();
        await createSummativeReport.toggleSummaryCurriculumProgression();
        await createSummativeReport.toggleClassCurriculumProgression();
        await createSummativeReport.fillClassCurriculumProgressionTitle("");
        await createSummativeReport.fillSummaryCurriculumProgressionTitle("");
        await createSummativeReport.clickSaveButton();
        tester.isGoingTo("check the mandatory fields validation messages are presented");
        await createSummativeReport.checkMandatoryFieldErrorMessagesArePresented();
        tester.isGoingTo("fill in the mandatory fields and save the summative report");
        await page.reload();
        await createSummativeReport.fillTitleField(summativeReportTitle);
        await createSummativeReport.fillPeriodsField("2020");
        await createSummativeReport.fillStudentSelectionField("year-levels", "9");
        await createSummativeReport.clickSaveButton();
        await summativeReportList.checkSummativeReportTitleIsPresented(summativeReportTitle);
        tester.isGoingTo("click the preview icon and check the summative report details");
        await summativeReportList.clickPreviewIcon(summativeReportTitle);
        await summativeReportList.checkPreviewReportDetails({
            title: summativeReportTitle,
            schoolName: "Alaress Pty Ltd",
            studentName: "Jana Reed",
            year: "Year 9",
        });
    });

    test("modify the summative report and preview it", async ({ tester, db }) => {
        tester.isGoingTo("modify the created summative report");
        await summativeReportList.goto();
        await summativeReportList.clickSummativeReportActionButton("modify", summativeReportTitle);
        await createSummativeReport.fillTitleField(modifiedTitle);
        await createSummativeReport.attachLogoImage(db, schoolLogo);
        await createSummativeReport.fillIntroductoryMessageField("This is an introductory message.");
        await createSummativeReport.deleteStudentSelectionFieldData("year-levels");
        await createSummativeReport.fillStudentSelectionField("students", "Penelope Li");
        await createSummativeReport.clickSaveButton();
        await summativeReportList.checkSummativeReportTitleIsPresented(modifiedTitle);
        tester.isGoingTo("click the preview icon and check the summative report details");
        await summativeReportList.clickPreviewIcon(modifiedTitle);
        await summativeReportList.checkPreviewReportDetails({
            title: modifiedTitle,
            schoolName: "Alaress Pty Ltd",
            studentName: "Penelope Li",
            year: "Year 9",
            introduction: "This is an introductory message.",
            schoolLogo,
        });
    });

    test("duplicate and delete the summative report", async ({ tester }) => {
        tester.isGoingTo("duplicate the modified summative report and check it is presented in the list");
        await summativeReportList.goto();
        await summativeReportList.clickSummativeReportActionButton("copy", modifiedTitle);
        const duplicatedTitle = `${modifiedTitle} (copy)`;
        await summativeReportList.checkSummativeReportTitleIsPresented(duplicatedTitle);
        tester.isGoingTo("click the preview icon and check the summative report details");
        await summativeReportList.clickPreviewIcon(duplicatedTitle);
        await summativeReportList.checkPreviewReportDetails({
            title: duplicatedTitle,
            schoolName: "Alaress Pty Ltd",
            studentName: "Penelope Li",
            year: "Year 9",
            introduction: "This is an introductory message.",
            schoolLogo,
        });
        tester.isGoingTo("delete the duplicated summative report and check it is no longer presented in the list");
        await summativeReportList.deleteSummativeReportByTitle(duplicatedTitle);
        await summativeReportList.checkSummativeReportIsDeleted(duplicatedTitle);
        tester.isGoingTo("reload the page to ensure the summative report is deleted permanently");
        await summativeReportList.goto();
        await summativeReportList.checkSummativeReportIsDeleted(duplicatedTitle);
    });

    /**
     * Soft deleting data to not impact the tests
     */
    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo(
            "update delete_at for the data created in this test so it does not impact next suite of tests",
        );
        await db.updateInDatabase(
            "summative_report",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Summative Report Initial Test%", deleted_at: null },
        );
    });
});
