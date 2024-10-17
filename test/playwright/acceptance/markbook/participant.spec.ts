/**
 * creates a due work, remove participants and check the excluded user for that activity is no longer presented in the class markbook
 * provision sales data
 * npx playwright test participant.spec.ts
 */

import path from "path";
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import MarkbookPage from "@schoolboxTest/playwright/support/page/markbook/MarkbookPage";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import SubmitForm from "@schoolboxTest/playwright/support/page/dueWork/SubmitForm";
import StatusPage from "@schoolboxTest/playwright/support/page/dueWork/StatusPage";
import Common from "@schoolboxTest/playwright/support/page/Common";

test.describe("Participant Tests", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.logout();
        await tester.loginAsAdmin();
    });

    /**
     * creates a due work, remove participants and check the excluded user for that activity is no longer presented in the class markbook
     */
    test("Participant", async ({ tester, page }) => {
        const classId = Common.CLASS_ID_DEMO2.toString();
        const studentId = Common.USER_ID_LOUISASMITH;
        const teacher = Common.USER_JACKCOLES;
        const teacherId = Common.USER_ID_JACKCOLES;

        const addComponent = new Homepage(page);
        const dueworkForm = new AssessmentForm(tester, page);
        const submitForm = new SubmitForm(tester, page);
        const markbookPage = new MarkbookPage(page);
        const statusPage = new StatusPage(tester, page);

        tester.isGoingTo("add a due work component into the homepage");
        await page.goto("/homepage/106");

        await tester.common.waitForPageLoad();

        await expect(page.locator(`h1:has-text("Demo Class Page 2 (DEMO2)")`)).toBeVisible();
        await addComponent.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_LEARNING, "Due Work");
        await expect(
            page.locator(
                `div[class="component-container Schoolbox_Learning_Assessment_DueWork_Component_Homepage_Controller"]`,
            ),
        ).toBeVisible();

        tester.isGoingTo("As Jack Coles, create a due work");
        await tester.impersonate(teacher);
        const dueworkTitle1 = `Cest One ${Common.genText(6)}`;
        const filename = path.join(__dirname, "../../support/file/test-80x80.png");

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

        tester.isGoingTo("As Jack Coles, create submission");
        await expect(page.locator(`h3:has-text("Cest One")`)).toBeVisible();
        await page.locator(`h3:has-text("Cest One")`).click();
        await page.getByTestId("assessment-summary-create-button").click();

        await submitForm.setContents(Common.genText(200));
        await submitForm.setAttachment(filename);
        await submitForm.submit();
        await expect(page.locator(`h2.subheader:has-text("Submission History")`)).toBeVisible();

        tester.isGoingTo("open the markbook and check if I can see my submission");
        await markbookPage.open(classId);
        await markbookPage.canSeeParticipant("Coles, Jack");
        await markbookPage.canSeeAssessment("Cest One");

        tester.isGoingTo("exclude Jack Coles from the due work participants list");
        await page.locator(`a:has-text("Cest One")`).click();
        await expect(page.locator(`h1:has-text("Status of Cest One")`)).toBeVisible();
        await page.waitForTimeout(2000);
        await statusPage.clickJellyTot();
        await statusPage.clickJellyTotOption("modify");
        await expect(page.locator(`h1:has-text("Update Cest One")`)).toBeVisible();
        await expect(page.getByTestId("assessment-form-button-submit-Update")).toBeEnabled();
        await page.waitForTimeout(2000);
        await dueworkForm.modifyParticipation(teacherId, true, true);
        await dueworkForm.update();
        await expect(page.locator(`h1:has-text("Status of Cest One")`)).toBeVisible();

        tester.isGoingTo(
            "open course mark book and check that Jack Coles is not presented and the student is presented",
        );
        await markbookPage.open(classId);
        await markbookPage.selectWorkType(1);
        await markbookPage.selectStatus("open");
        await markbookPage.applyFilter();
        await markbookPage.canSeeAssessment("Cest One");
        await markbookPage.cantSeeParticipant("Coles, Jack");
        await markbookPage.canSeeParticipant("Smith, Louisa");

        tester.isGoingTo("exclude a student (Louisa Smith) from the due work participants list");
        await page.locator(`a:has-text("Cest One")`).click();
        await expect(page.locator(`h1:has-text("Status of Cest One")`)).toBeVisible();
        await page.waitForTimeout(2000);
        await statusPage.clickJellyTot();
        await statusPage.clickJellyTotOption("modify");
        await expect(page.locator(`h1:has-text("Update Cest One")`)).toBeVisible();
        await expect(page.getByTestId("assessment-form-button-submit-Update")).toBeEnabled();
        await dueworkForm.modifyParticipation(studentId, false, false);
        await dueworkForm.update();
        await expect(page.locator(`h1:has-text("Status of Cest One")`)).toBeVisible();

        tester.isGoingTo(
            "open course mark book and check that Jack Coles is not presented and Louisa Smith is presented",
        );
        await markbookPage.open(classId);
        await markbookPage.canSeeAssessment("Cest One");
        await markbookPage.cantSeeParticipant("Coles, Jack");
        await markbookPage.canSeeParticipant("Smith, Louisa");

        // remove the duework component from the homepage to avoid issues in the next test
        tester.isGoingTo("remove the duework component from the homepage");
        await page.goto("/homepage/106");
        await page.waitForTimeout(2000);
        await addComponent.clickLockToggle();
        await addComponent.clickComponentRemoveIcon();
        await addComponent.clickRemoveConfirmationMessage();
    });

    /**
     * remove the duework component from the homepage and delete the assessment
     */

    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("delete the assessment");
        await db.updateInDatabase("assessment", { deleted_at: "2022-01-15 11:22:34" }, { "title like": "Cest One%" });
    });
});
