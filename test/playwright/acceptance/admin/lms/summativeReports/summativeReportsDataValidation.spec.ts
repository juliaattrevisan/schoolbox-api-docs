import { test } from "@schoolboxTest/playwright/support/integrations";
import CreateSummativeReport from "@schoolboxTest/playwright/support/page/admin/lms/summativeReports/CreateSummativeReport";
import SummativeList from "@schoolboxTest/playwright/support/page/admin/lms/summativeReports/SummativeReportList";
import SummativePreview from "@schoolboxTest/playwright/support/page/admin/lms/summativeReports/SummativePreview";
import Common from "@schoolboxTest/playwright/support/page/Common";
/**
 * create a summative report with summary and class inclusion
 * check the heading works properly in the preview page
 * check the show overall grade works properly in the preview page
 * check the show class code works properly in the preview page
 * check the show, grade, comment, rubric and distribution graph works properly in the preview page
 * check when the summary page and class page are toggled off then it is not presented in the preview page
 * check it is possible to download the report: the message informing the report is being generated is shown
 * clicking the notification on the side panel downloads the report
 */

test.describe("Admin LMS - Summative Reports Data Validation", () => {
    let summativeReportList: SummativeList;
    let createSummativeReport: CreateSummativeReport;
    let common: Common;

    const summativeReportTitle = "Summative Report Data Validation Test";
    const academicPeriod = "2020";
    const yearLevel = "9";
    const summaryHeading = "Summary Heading";
    const subjectOverviewTitle = "Course page subject overview";

    test.beforeEach(async ({ tester, page }) => {
        summativeReportList = new SummativeList(tester, page);
        createSummativeReport = new CreateSummativeReport(tester, page);
        common = new Common(page);
        await tester.loginAsAdmin();
    });

    test.skip("create a summative report with summary and class inclusion and check the report details in the preview page", async ({
        tester,
        db,
    }) => {
        try {
            tester.isGoingTo(
                "navigate to create summative report page and fill the title, academic period, year level, heading and summary work types",
            );
            await createSummativeReport.goto();
            await createSummativeReport.fillTitleField(summativeReportTitle);
            await createSummativeReport.fillPeriodsField(academicPeriod);
            await createSummativeReport.fillStudentSelectionField("year-levels", yearLevel);
            await createSummativeReport.toggleSummaryInclusion();
            await createSummativeReport.fillSummaryHeadingField(summaryHeading);
            await createSummativeReport.tickSummaryOverallGradeClassCodeCheckboxes(true, false);
            await createSummativeReport.tickSummaryWorkTypeCheckboxes("Essay", ["show", "grades", "comments"]);
            await createSummativeReport.tickSummaryWorkTypeCheckboxes("Oral", ["show", "rubrics", "graph"]);
            await createSummativeReport.clickSaveButton();

            tester.isGoingTo("check the summary report details are correct in the preview page");
            const previewPage = await summativeReportList.clickPreviewIcon(summativeReportTitle);
            const summativeReportPreview = new SummativePreview(tester, previewPage);
            await summativeReportPreview.checkSummaryHeading(summaryHeading);
            await summativeReportPreview.checkClassDistributionTitle();
            await summativeReportPreview.checkDistributionGraphIsPresented();
            await summativeReportPreview.checkDistributionGraphForActivityType("Present a scene of Romeo and Juliet");
            await summativeReportPreview.checkResultsTitle();
            await summativeReportPreview.checkFeedbackContainersForAssessments([
                "Essay - Final Assessment",
                "Present a scene of Romeo and Juliet",
            ]);
            await summativeReportPreview.checkFeedbackContainerDetails(
                "Essay - Final Assessment",
                ["grade", "comment"],
                true,
            );
            await summativeReportPreview.checkFeedbackContainerDetails("Essay - Final Assessment", ["rubric"], false);
            await summativeReportPreview.checkFeedbackContainerDetails(
                "Present a scene of Romeo and Juliet",
                ["rubric"],
                true,
            );
            await summativeReportPreview.checkFeedbackContainerDetails(
                "Present a scene of Romeo and Juliet",
                ["grade", "comment"],
                false,
            );
        } finally {
            tester.isGoingTo(
                "update delete_at for the data created in this test so it does not impact next suite of tests",
            );
            await db.updateInDatabase(
                "summative_report",
                { deleted_at: "2022-01-15 11:22:34" },
                { "title like": summativeReportTitle, deleted_at: null },
            );
        }
    });

    test.skip("create a summative report page, toggle on the class page to check its details", async ({
        tester,
        db,
    }) => {
        try {
            tester.isGoingTo("create a summative report page, toggle on the class page");
            await createSummativeReport.goto();
            await createSummativeReport.fillTitleField(summativeReportTitle);
            await createSummativeReport.fillPeriodsField(academicPeriod);
            await createSummativeReport.fillStudentSelectionField("year-levels", yearLevel);
            await createSummativeReport.toggleClassInclusion();
            await createSummativeReport.tickClassOverallGradeClassCodeCheckboxes(true, false);
            await createSummativeReport.typeSubjectOverview(true, subjectOverviewTitle);
            await createSummativeReport.tickClassWorkTypeCheckboxes("Essay", ["show", "grades", "comments"]);
            await createSummativeReport.tickClassWorkTypeCheckboxes("Oral", ["show", "rubrics", "graph"]);
            await createSummativeReport.clickSaveButton();

            tester.isGoingTo("check the class report details are correct in the preview page");
            const previewPage = await summativeReportList.clickPreviewIcon(summativeReportTitle);
            const summativeReportPreview = new SummativePreview(tester, previewPage);
            await summativeReportPreview.checkClassDistributionTitle();
            await summativeReportPreview.checkDistributionGraphIsPresented();
            await summativeReportPreview.checkDistributionGraphForActivityType("Present a scene of Romeo and Juliet");
            await summativeReportPreview.checkFeedbackContainersForAssessments([
                "Essay - Final Assessment",
                "Present a scene of Romeo and Juliet",
            ]);
            await summativeReportPreview.checkFeedbackContainerDetails(
                "Essay - Final Assessment",
                ["grade", "comment"],
                true,
            );
            await summativeReportPreview.checkFeedbackContainerDetails("Essay - Final Assessment", ["rubric"], false);
            await summativeReportPreview.checkFeedbackContainerDetails(
                "Present a scene of Romeo and Juliet",
                ["rubric"],
                true,
            );
            await summativeReportPreview.checkFeedbackContainerDetails(
                "Present a scene of Romeo and Juliet",
                ["grade", "comment"],
                false,
            );
        } finally {
            tester.isGoingTo(
                "update delete_at for the data created in this test so it does not impact next suite of tests",
            );
            await db.updateInDatabase(
                "summative_report",
                { deleted_at: "2022-01-15 11:22:34" },
                { "title like": summativeReportTitle, deleted_at: null },
            );
        }
    });

    test("create a summative report page, click download, check the notification is shown and able to download the report from the notification", async ({
        tester,
        db,
        page,
    }) => {
        try {
            tester.isGoingTo("clear all notifications");
            await tester.runShellCommand(
                `php /usr/share/schoolbox/test/socket/destroy-notification-redis-data.php -vvv`,
            );

            tester.isGoingTo("create a summative report page");
            await createSummativeReport.goto();
            await createSummativeReport.fillTitleField(summativeReportTitle);
            await createSummativeReport.fillPeriodsField(academicPeriod);
            await createSummativeReport.fillStudentSelectionField("students", "Jana");
            await createSummativeReport.clickSaveButton();

            tester.isGoingTo(
                "click the download icon and check the notification is shown and able to download the report from the notification",
            );
            await summativeReportList.clickSummativeReportActionButton("download", summativeReportTitle);
            await summativeReportList.checkReportGenerationInProgressMessage(1);
            tester.isGoingTo("wait for a while for the notification to trigger");
            await page.waitForSelector(`a[data-unread="1"]`, { timeout: 120000 });
            await common.assertNotifExists(`Your summative report ${summativeReportTitle} is ready to download`);
            await common.assertFileDownload("notification-image", `${summativeReportTitle}.zip`);
        } finally {
            tester.isGoingTo(
                "update delete_at for the data created in this test so it does not impact next suite of tests",
            );
            await db.updateInDatabase(
                "summative_report",
                { deleted_at: "2022-01-15 11:22:34" },
                { "title like": summativeReportTitle, deleted_at: null },
            );
            tester.isGoingTo("clear all notifications");
            await tester.runShellCommand(
                `php /usr/share/schoolbox/test/socket/destroy-notification-redis-data.php -vvv`,
            );
        }
    });
});
