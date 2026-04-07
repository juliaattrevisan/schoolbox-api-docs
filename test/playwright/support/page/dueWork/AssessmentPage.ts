import { expect } from "@playwright/test";
import type { Locator, Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import AssessmentForm from "./AssessmentForm";

export default class AssessmentPage {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/learning/assessments/{assessment_id}/{user_id}";

    static readonly DATA_TEST_LINK_JELLY_TOT = "sbx-pop-out-header-options";

    static readonly SELECTOR_LINK_MODIFY = '#header-options a:has(sbx-icon[name="modify"])';

    static readonly SELECTOR_LINK_STATUS = "#header-options a.icon-progress";

    static readonly DATA_TEST_ASSESSMENT_SUMMARY_CREATE_BUTTON = "assessment-summary-create-button";

    static readonly DATA_TEST_ASSESSMENT_SUMMARY_SELF_ACKNOWLEDGE_BUTTON = "assessment-summary-self-acknowledge-button";

    static readonly DATA_TEST_ASSESSMENT_SUBMISSION_HISTORY = "assessment-submission-history";

    static readonly DATA_TEST_ASSESSMENT_SUBMISSION_HISTORY_INTERACTION = "assessment-submission-history-interaction-"; // append "{interactionId}"

    static readonly SELECTOR_RETURN_BUTTON = 'a:has-text("Return")';

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Go to the assessment page for a given assessment and user
     * @param assessmentId - The assessment ID
     * @param userId - The user ID
     */
    async goto(assessmentId: number, userId: number): Promise<void> {
        const targetUrl = AssessmentPage.URL.replace("{assessment_id}", assessmentId.toString()).replace(
            "{user_id}",
            userId.toString(),
        );
        await this.page.goto(targetUrl);
    }

    /**
     * Navigates to modify page
     */
    async clickModify(): Promise<void> {
        await this.page.getByTestId(AssessmentPage.DATA_TEST_LINK_JELLY_TOT).click();
        await this.page.locator(AssessmentPage.SELECTOR_LINK_MODIFY).click();
        await expect(this.page.getByTestId(AssessmentForm.SELECTOR_BUTTON_UPDATE)).toBeVisible();
    }

    /**
     * Navigates to inline grader for the student
     * @param student - The student's name
     * @param title - The assessment title
     */
    async openInlineGrader(student: string, title: string): Promise<void> {
        await this.page
            .getByTestId(AssessmentPage.DATA_TEST_ASSESSMENT_SUBMISSION_HISTORY)
            .getByText(`Submission by ${student}`)
            .click();
        await this.page.waitForSelector(`text=Grading ${title}`);
    }

    /**
     * Navigates to duework status page
     */
    async openStatusPage(): Promise<void> {
        await this.page.getByTestId(AssessmentPage.DATA_TEST_LINK_JELLY_TOT).click();
        await this.page.locator(AssessmentPage.SELECTOR_LINK_STATUS).click();
    }

    /**
     * click Create submission button
     */
    async clickCreateSubmission(): Promise<void> {
        await this.page.getByTestId(AssessmentPage.DATA_TEST_ASSESSMENT_SUMMARY_CREATE_BUTTON).click();
    }

    /**
     * expect the create submission button to be disabled
     */
    async expectCreateSubmissionDisabled(): Promise<void> {
        const createButton = this.page.getByTestId(AssessmentPage.DATA_TEST_ASSESSMENT_SUMMARY_CREATE_BUTTON);
        await expect(createButton).toBeDisabled();
    }

    /**
     * Click the self-acknowledge button (i.e. "I have submitted my work in person")
     */
    async clickSelfAcknowledge(): Promise<void> {
        await this.page.getByTestId(AssessmentPage.DATA_TEST_ASSESSMENT_SUMMARY_SELF_ACKNOWLEDGE_BUTTON).click();
    }

    /**
     * Click the "Proceed" button in the modal after being prompted for "Have you submitted your work?"
     * via the self-acknowledge button on the assessment summary page
     */
    async clickSelfAcknowledgeProceed(): Promise<void> {
        await this.tester.common.waitForModalToBeOpen();
        await this.tester.common.clickConfirmButton();
        await this.tester.common.waitForModalToBeClosed();
    }

    /**
     * Clicks the return button to go back to the previous page
     */
    async clickReturn(): Promise<void> {
        await this.page.locator(AssessmentPage.SELECTOR_RETURN_BUTTON).click();
    }

    /**
     * @returns A Locator for all interactions in the submission history section
     */
    public locateAllInteractions(): Locator {
        const anyInteraction = new RegExp(`^${AssessmentPage.DATA_TEST_ASSESSMENT_SUBMISSION_HISTORY_INTERACTION}`);
        return this.page.getByTestId(anyInteraction);
    }
}
