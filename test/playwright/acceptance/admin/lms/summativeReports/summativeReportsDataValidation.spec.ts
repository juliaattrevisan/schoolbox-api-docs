import { test } from "@schoolboxTest/playwright/support/integrations";
import CreateSummativeReport from "@schoolboxTest/playwright/support/page/admin/lms/summativeReports/CreateSummativeReport";
import SummativeList from "@schoolboxTest/playwright/support/page/admin/lms/summativeReports/SummativeReportList";
import SummativePreview from "@schoolboxTest/playwright/support/page/admin/lms/summativeReports/SummativePreview";

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

test.describe.serial("Admin LMS - Summative Reports Data Validation", () => {
    let summativeReportList: SummativeList;
    let createSummativeReport: CreateSummativeReport;

    const summativeReportTitle = "Summative Report Data Validation Test";
    const academicPeriod = "2020";
    const yearLevel = "9";
    const summaryHeading = "Summary Heading";

    test.beforeEach(async ({ tester, page }) => {
        summativeReportList = new SummativeList(tester, page);
        createSummativeReport = new CreateSummativeReport(tester, page);
        await tester.loginAsAdmin();
    });

    test("create a summative report with summary and class inclusion and check the report details in the preview page", async ({
        tester,
    }) => {
        tester.isGoingTo(
            "navigate to create summative report page and fill the title, academic period, year level, heading and summary work types",
        );
        await summativeReportList.goto();
        await summativeReportList.clickCreateSummativeReportButton();
        await createSummativeReport.fillTitleField(summativeReportTitle);
        await createSummativeReport.fillPeriodsField(academicPeriod);
        await createSummativeReport.fillStudentSelectionField("year-levels", yearLevel);
        await createSummativeReport.toggleSummaryInclusion();
        await createSummativeReport.fillSummaryHeadingField(summaryHeading);
        await createSummativeReport.tickOverallGradeClassCodeCheckboxes(true, false);
        await createSummativeReport.tickWorkTypeCheckboxes("Essay", ["show", "grades", "comments"]);
        await createSummativeReport.tickWorkTypeCheckboxes("Oral", ["show", "rubrics", "graph"]);
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
    });
});
