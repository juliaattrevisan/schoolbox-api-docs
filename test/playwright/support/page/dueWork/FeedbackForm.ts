import { Page } from "@playwright/test";
import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import Common from "@schoolboxTest/playwright/support/page/Common";

export default class FeedbackForm {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/learning/assessments/{assessment_id}/{user_id}/responses/{response_id}";

    static readonly SELECTOR_GRADE_INPUT = "input[name='mark']";

    static readonly SELECTOR_COMMENT_TEXTAREA = "textarea[name='comment']";

    static readonly SELECTOR_ATTACHMENT_ZONE = "#feedbackFiles-zone";

    static readonly DATATEST_ASSESSMENT_FEEDBACK_PUBLISH_BUTTON = "assessment-feedback-publish-button";

    static readonly DATATEST_ASSESSMENT_RESPONSE_INFO_SAVED_AS_DRAFT = "assessment-response-info-saved-as-draft";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Go to the feedback form for a given assessment, user and response
     * @param assessmentId - The assessment ID
     * @param userId - The user ID
     * @param responseId - The response ID
     */
    async goto(assessmentId: number, userId: number, responseId: number): Promise<void> {
        const targetUrl = FeedbackForm.URL.replace("{assessment_id}", assessmentId.toString())
            .replace("{user_id}", userId.toString())
            .replace("{response_id}", responseId.toString());
        await this.page.goto(targetUrl);
    }

    /**
     * Sets grade in the feedback form
     * @param grade - The grade to set
     */
    async setGrade(grade: number): Promise<void> {
        await this.page.fill(FeedbackForm.SELECTOR_GRADE_INPUT, grade.toString());
    }

    /**
     * Sets comment in the feedback form
     * @param comment - The comment to set
     */
    async setComment(comment: string): Promise<void> {
        await this.page.fill(FeedbackForm.SELECTOR_COMMENT_TEXTAREA, Common.genText(200));
        await this.page.waitForTimeout(Math.random() * 2000 + 1000);
        await this.page.fill(FeedbackForm.SELECTOR_COMMENT_TEXTAREA, Common.genText(200));
        await this.page.waitForTimeout(Math.random() * 2000 + 1000);
        await this.page.fill(FeedbackForm.SELECTOR_COMMENT_TEXTAREA, comment);
        await this.page.waitForTimeout(Math.random() * 2000 + 1000);
    }

    /**
     * Sets attachment in the feedback form
     * @param filePath - The file to attach
     */
    async setAttachment(filePath: string): Promise<void> {
        const attachZone = this.page.locator(FeedbackForm.SELECTOR_ATTACHMENT_ZONE);
        await this.tester.dragAndDropFile(attachZone, filePath);
    }

    /**
     * Publishes the feedback
     */
    async publish(): Promise<void> {
        await this.page.getByTestId(FeedbackForm.DATATEST_ASSESSMENT_RESPONSE_INFO_SAVED_AS_DRAFT).waitFor();
        await this.page.waitForTimeout(2000); // Wait for auto-saving to finish
        await this.page.getByTestId(FeedbackForm.DATATEST_ASSESSMENT_FEEDBACK_PUBLISH_BUTTON).click();
        await this.page.waitForTimeout(1000); // Wait for the feedback to be published
    }
}
