import { test } from "@schoolboxTest/playwright/support/integrations";
import CreateSummativeReport from "@schoolboxTest/playwright/support/page/admin/lms/summativeReports/CreateSummativeReport";
import SummativeList from "@schoolboxTest/playwright/support/page/admin/lms/summativeReports/SummativeReportList";
import SummativePreview from "@schoolboxTest/playwright/support/page/admin/lms/summativeReports/SummativePreview";
import type { Page } from "@playwright/test";

/**
 * create summative reports for Staff, Student and Parent views
 * check work type activity audience visibility is respected in the preview page
 * 1 Provision sales.tar.gz data
 * Execute DOCKER=1 npx playwright test test/playwright/acceptance/admin/lms/summativeReports/summativeReportsVisibility.spec.ts
 */

test.describe("Admin LMS - Summative Reports Visibility - View As field", () => {
    let summativeReportList: SummativeList;
    let createSummativeReport: CreateSummativeReport;

    const academicPeriod = "2020";
    const yearLevel = "9";
    const summativeReportTitle = "Summative Report Visibility Test";
    const workTypes = ["Assessment Task", "Essay", "Class Work", "Oral"];
    const assessmentTaskTitle = "Persuasive Assessment Task (Group Work)";
    const assessmentEssayTitle = "Essay - Final Assessment";
    const assessmentClassWorkTitle = "Persuasive Article";
    const assessmentOralTitle = "Present a scene of Romeo and Juliet";

    test.beforeAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("update work type visibility for summative report visibility tests");
        await db.updateInDatabase("lu_work_type", { is_visible_to_parents: 0, is_visible_to_learners: 0 }, { id: 1 });
        await db.updateInDatabase("lu_work_type", { is_visible_to_parents: 0, is_visible_to_learners: 1 }, { id: 5 });
        await db.updateInDatabase("lu_work_type", { is_visible_to_parents: 1, is_visible_to_learners: 0 }, { id: 7 });
        await db.updateInDatabase("lu_work_type", { is_visible_to_parents: 1, is_visible_to_learners: 1 }, { id: 9 });
    });

    test.beforeEach(async ({ tester, page }) => {
        summativeReportList = new SummativeList(tester, page);
        createSummativeReport = new CreateSummativeReport(tester, page);
        await tester.loginAsAdmin();
    });

    test("check the summative report with view as: staff", async ({ tester, db }) => {
        let previewPage: Page | undefined;
        const title = `${summativeReportTitle} - Staff`;

        try {
            tester.isGoingTo("create a summative report for staff visibility");
            await createSummativeReport.createReportForViewAs(title, "Staff", academicPeriod, yearLevel, workTypes);

            tester.isGoingTo("preview the staff summative report and check all work types are presented");
            previewPage = await summativeReportList.clickPreviewIcon(title);
            const summativeReportPreview = new SummativePreview(tester, previewPage);
            await summativeReportPreview.checkFeedbackContainersForAssessmentsCount(
                [assessmentTaskTitle, assessmentEssayTitle, assessmentClassWorkTitle, assessmentOralTitle],
                2,
            );
        } finally {
            await previewPage?.close();
            await db.updateInDatabase(
                "summative_report",
                { deleted_at: "2022-01-15 11:22:34" },
                { "title like": title, deleted_at: null },
            );
        }
    });

    test("check the summative report with view as: student", async ({ tester, db }) => {
        let previewPage: Page | undefined;
        const title = `${summativeReportTitle} - Student`;

        try {
            tester.isGoingTo("create a summative report for student visibility");
            await createSummativeReport.createReportForViewAs(title, "Student", academicPeriod, yearLevel, workTypes);

            tester.isGoingTo("preview the student summative report and check student-visible work types are presented");
            previewPage = await summativeReportList.clickPreviewIcon(title);
            const summativeReportPreview = new SummativePreview(tester, previewPage);
            await summativeReportPreview.checkFeedbackContainersForAssessmentsCount(
                [assessmentEssayTitle, assessmentOralTitle],
                2,
            );
            await summativeReportPreview.checkFeedbackContainersForAssessmentsCount(
                [assessmentTaskTitle, assessmentClassWorkTitle],
                0,
            );
        } finally {
            await previewPage?.close();
            await db.updateInDatabase(
                "summative_report",
                { deleted_at: "2022-01-15 11:22:34" },
                { "title like": title, deleted_at: null },
            );
        }
    });

    test("check the summative report with view as: parent", async ({ tester, db }) => {
        let previewPage: Page | undefined;
        const title = `${summativeReportTitle} - Parent`;

        try {
            tester.isGoingTo("create a summative report for parent visibility");
            await createSummativeReport.createReportForViewAs(title, "Parent", academicPeriod, yearLevel, workTypes);

            tester.isGoingTo("preview the parent summative report and check parent-visible work types are presented");
            previewPage = await summativeReportList.clickPreviewIcon(title);
            const summativeReportPreview = new SummativePreview(tester, previewPage);
            await summativeReportPreview.checkFeedbackContainersForAssessmentsCount(
                [assessmentClassWorkTitle, assessmentOralTitle],
                2,
            );
            await summativeReportPreview.checkFeedbackContainersForAssessmentsCount(
                [assessmentTaskTitle, assessmentEssayTitle],
                0,
            );
        } finally {
            await previewPage?.close();
            await db.updateInDatabase(
                "summative_report",
                { deleted_at: "2022-01-15 11:22:34" },
                { "title like": title, deleted_at: null },
            );
        }
    });

    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("reset work type visibility changed for summative report visibility tests");
        await db.updateInDatabase("lu_work_type", { is_visible_to_parents: 1, is_visible_to_learners: 1 }, { id: 1 });
        await db.updateInDatabase("lu_work_type", { is_visible_to_parents: 1, is_visible_to_learners: 1 }, { id: 5 });
        await db.updateInDatabase("lu_work_type", { is_visible_to_parents: 1, is_visible_to_learners: 1 }, { id: 7 });
        await db.updateInDatabase("lu_work_type", { is_visible_to_parents: 1, is_visible_to_learners: 1 }, { id: 9 });
        await db.updateInDatabase(
            "summative_report",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": `${summativeReportTitle}%`, deleted_at: null },
        );
    });
});
