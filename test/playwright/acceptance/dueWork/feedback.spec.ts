import path from "path";
import { expect, test } from "@schoolboxTest/playwright/support/integrations";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import AssessmentPage from "@schoolboxTest/playwright/support/page/dueWork/AssessmentPage";
import FeedbackForm from "@schoolboxTest/playwright/support/page/dueWork/FeedbackForm";
import StatusPage from "@schoolboxTest/playwright/support/page/dueWork/StatusPage";
import SubmitForm from "@schoolboxTest/playwright/support/page/dueWork/SubmitForm";
import Common from "@schoolboxTest/playwright/support/page/Common";

const SELECTOR_ASSESSMENT_SUMMARY_CREATE_BUTTON = "assessment-summary-create-button";

test.describe("FeedbackCest", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.logout();
        await tester.loginAsAdmin();
    });

    /**
     * Creates and submits a due work, marks it via the inline grader,
     * and checks notifications for it at each step
     */
    test("due work via inline grader", async ({ tester, page }) => {
        const common = new Common(page);

        const className = Common.CLASS_9AENG;
        const classId = Common.CLASS_ID_9AENG;
        const student = Common.USER_LOUISASMITH;
        const teacher = Common.USER_JACKCOLES;
        const dueworkTitle1 = `Cest One ${Common.genText(6)}`;
        const filename = path.join(__dirname, "../../support/file/test-80x80.png");

        tester.isGoingTo("As Jack Coles, create a due work");
        await tester.impersonate(teacher);

        const dueworkForm = new AssessmentForm(tester, page);
        await dueworkForm.goto(classId.toString());

        await dueworkForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
        await dueworkForm.setAcceptSubmission(AssessmentForm.ACCEPT_SUBMISSION_MULTIPLE);
        await dueworkForm.checkSubmissionAllowed(AssessmentForm.ALLOW_OFFLINE);
        await dueworkForm.checkSubmissionAllowed(AssessmentForm.TEXT_EDITOR);
        await dueworkForm.checkSubmissionAllowed(AssessmentForm.TYPE_FILES);
        await dueworkForm.setTitle(dueworkTitle1);
        await dueworkForm.setPublishDate(Common.getTodayAtMidnight());
        await dueworkForm.setOpenDate(Common.getTodayAtMidnight());
        await dueworkForm.setMarkType(AssessmentForm.MARK_TYPE_PERCENTAGE);
        await dueworkForm.create();

        tester.isGoingTo("As Louisa Smith, check notification");
        await tester.impersonateViaModifyUserPage(student);
        const assessmentNotificationLouisa = await common.findNotification(
            `Assessment Task ${dueworkTitle1} opened in ${className}`,
            true,
        );

        tester.isGoingTo("Go to submit page");
        await assessmentNotificationLouisa.getByText(dueworkTitle1).click();
        await page.getByTestId(SELECTOR_ASSESSMENT_SUMMARY_CREATE_BUTTON).click();
        await expect(page.locator("#assessment-status h1").first()).toHaveText(dueworkTitle1);

        tester.isGoingTo("create submission");
        const submitForm = new SubmitForm(tester, page);
        await submitForm.setContents(Common.genText(200));
        await submitForm.setAttachment(filename);
        await submitForm.submit();

        tester.isGoingTo("As Jack Coles, check the notification");
        await tester.impersonate(teacher);
        const assessmentNotificationJack = await common.findNotification(
            `${student} submitted work to ${dueworkTitle1} in ${className}`,
            true,
        );

        tester.isGoingTo("As Jack Coles, click into the due work");
        await assessmentNotificationJack.getByText(dueworkTitle1).click();
        await expect(page.locator("h2.subheader").filter({ hasText: "SUBMISSION HISTORY" })).toBeVisible();

        tester.isGoingTo("As Jack Coles, click to the students submission page");
        const dueworkPage = new AssessmentPage(tester, page);
        await dueworkPage.openInlineGrader(student, dueworkTitle1);

        const grade = Math.floor(Math.random() * 50) + 50; // Random grade between 50 and 100

        const feedbackForm = new FeedbackForm(tester, page);
        await feedbackForm.setGrade(grade);
        await feedbackForm.setComment(Common.genText(200));
        await feedbackForm.publish();

        tester.isGoingTo("As Louisa Smith, check notification");
        await tester.impersonateViaModifyUserPage(student);
        const assessmentNotificationLuisa2 = await common.findNotification(
            `${teacher} has marked your work for ${dueworkTitle1} in ${className}`,
            true,
        );

        tester.isGoingTo("As Louisa Smith, click into the due work");
        await assessmentNotificationLuisa2.getByText(dueworkTitle1).click();
        await expect(page.locator("h2.subheader").filter({ hasText: "GRADING" })).toBeVisible();
    });

    /**
     * Creates and submits a due work, marks it via the activity status page,
     * and checks notifications for it at each step
     */
    test("due work via activity status page", async ({ tester, page }) => {
        const common = new Common(page);

        const className = Common.CLASS_9AENG;
        const classId = Common.CLASS_ID_9AENG;
        const student = Common.USER_LOUISASMITH;
        const studentId = Common.USER_ID_LOUISASMITH;
        const teacher = Common.USER_JACKCOLES;
        const dueworkTitle2 = `Cest Two ${Common.genText(6)}`;

        tester.isGoingTo("As Jack Coles, create a due work");
        await tester.impersonate(teacher);
        const dueworkForm = new AssessmentForm(tester, page);
        await dueworkForm.goto(classId.toString());

        await dueworkForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
        await dueworkForm.setAcceptSubmission(AssessmentForm.ACCEPT_SUBMISSION_MULTIPLE);
        await dueworkForm.checkSubmissionAllowed(AssessmentForm.ALLOW_OFFLINE);
        await dueworkForm.checkSubmissionAllowed(AssessmentForm.TEXT_EDITOR);
        await dueworkForm.checkSubmissionAllowed(AssessmentForm.TYPE_FILES);
        await dueworkForm.setTitle(dueworkTitle2);
        await dueworkForm.setPublishDate(Common.getTodayAtMidnight());
        await dueworkForm.setOpenDate(Common.getTodayAtMidnight());
        await dueworkForm.setMarkType(AssessmentForm.MARK_TYPE_PERCENTAGE);
        await dueworkForm.create();

        tester.isGoingTo("As Louisa Smith, check notification");
        await tester.impersonateViaModifyUserPage(student);
        const assessmentNotificationLouisa = await common.findNotification(
            `Assessment Task ${dueworkTitle2} opened in ${className}`,
            true,
        );

        tester.isGoingTo("Go to the submit page");
        await assessmentNotificationLouisa.getByText(dueworkTitle2).click();
        await page.getByTestId(SELECTOR_ASSESSMENT_SUMMARY_CREATE_BUTTON).click();
        await expect(page.locator("#assessment-status h1").first()).toHaveText(dueworkTitle2);

        tester.isGoingTo("create submission");
        const submitForm = new SubmitForm(tester, page);
        await submitForm.setContents(Common.genText(200));
        await submitForm.submit();

        tester.isGoingTo("As Jack Coles, check notification");
        await tester.impersonate(teacher);
        const assessmentNotificationJack = await common.findNotification(
            `${student} submitted work to ${dueworkTitle2} in ${className}`,
            true,
        );

        tester.isGoingTo("As Jack Coles, click into the due work");
        await assessmentNotificationJack.getByText(dueworkTitle2).click();
        await expect(page.locator("h2.subheader").filter({ hasText: "SUBMISSION HISTORY" })).toBeVisible();

        tester.isGoingTo("Now go to duework status page");
        const dueworkPage = new AssessmentPage(tester, page);
        await dueworkPage.openStatusPage();

        const grade = Math.floor(Math.random() * 50) + 50; // Random grade between 50 and 100

        const statusPage = new StatusPage(tester, page);
        await statusPage.gradeSubmission(studentId, grade, Common.genText(200));

        tester.isGoingTo("find Louisa's submission and enter feedback");
        await expect(page.locator(`[data-participant="${studentId}"] [data-assessment-status-text]`)).toHaveText(
            "Reviewed",
        );

        tester.isGoingTo("As Louisa, check notification");
        await tester.impersonateViaModifyUserPage(student);
        const assessmentNotificationLuisa2 = await common.findNotification(
            `${teacher} has marked your work for ${dueworkTitle2} in ${className}`,
            true,
        );

        tester.isGoingTo("As Louisa, click into the due work");
        await assessmentNotificationLuisa2.getByText(dueworkTitle2).click();
        await expect(page.locator("h2.subheader").filter({ hasText: "GRADING" })).toBeVisible();
    });

    test.afterEach(async ({ tester, db }) => {
        tester.isGoingTo("update delete_at for the data created in this test so it does impact next suite of tests");
        await db.updateInDatabase("assessment", { deleted_at: "2022-01-15 11:22:34" }, { "title LIKE": "Cest One%" });
        await db.updateInDatabase("assessment", { deleted_at: "2022-01-15 11:22:34" }, { "title LIKE": "Cest Two%" });
        await db.updateInDatabase(
            "lesson_plan_template",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title LIKE": "Automation Template%" },
        );
    });
});
