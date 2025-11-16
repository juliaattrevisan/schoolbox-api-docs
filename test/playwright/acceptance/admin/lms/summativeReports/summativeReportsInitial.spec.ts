import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CreateSummativeReport from "@schoolboxTest/playwright/support/page/admin/lms/summativeReports/CreateSummativeReport";
import SummativeList from "@schoolboxTest/playwright/support/page/admin/lms/summativeReports/SummativeReportList";

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

    test.beforeEach(async ({ tester, page }) => {
        summativeReportList = new SummativeList(tester, page);
        createSummativeReport = new CreateSummativeReport(tester, page);
        await tester.loginAsAdmin();
    });

    test("check the summative empty message and mandatory fields validation; create a summative report", async ({
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
        await createSummativeReport.fillCampusField("Secondary");
        await createSummativeReport.clickSaveButton();
        await summativeReportList.checkSummativeReportTitleIsPresented(summativeReportTitle);
    });

    test("modify the summative report", async ({ tester, page }) => {
        tester.isGoingTo("modify the created summative report");
        await summativeReportList.goto();
        await summativeReportList.clickSummativeReportActionButton("modify", summativeReportTitle);
        await createSummativeReport.fillTitleField(modifiedTitle);
        await createSummativeReport.fillIntroductoryMessageField("This is an introductory message.");
        await page.locator("input#report-campuses-selectized").click();
        await page.keyboard.press("Backspace");
        await createSummativeReport.fillStudentSelectionField("Penelope Li");
        await createSummativeReport.clickSaveButton();
        await summativeReportList.checkSummativeReportTitleIsPresented(modifiedTitle);
        // Ms Penelope Li - Year 9, Middle Student (511)
    });
});
