import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type DatabaseHelper from "@schoolboxTest/playwright/support/DatabaseHelper";

export default class FeedbackForm {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/learning/assessments/{assessment_id}/{user_id}/responses/{response_id}";

    static readonly SELECTOR_ATTACHMENT_ZONE = "#feedbackFiles-zone";

    static readonly DATA_TEST_ASSESSMENT_FEEDBACK_MARK_INPUT = "assessment-feedback-mark-input"; // Append "-[userId]"

    static readonly DATA_TEST_ASSESSMENT_FEEDBACK_MARK_SELECT = "assessment-feedback-mark-select"; // Append "-[userId]"

    static readonly DATA_TEST_ASSESSMENT_FEEDBACK_COMMENT_TEXTAREA = "assessment-feedback-comment-textarea"; // Append "-[userId]"

    static readonly DATA_TEST_ASSESSMENT_FEEDBACK_PUBLISH_BUTTON = "assessment-feedback-publish-button";

    static readonly DATA_TEST_ASSESSMENT_RESPONSE_INFO_SAVED_AS_DRAFT = "assessment-response-info-saved-as-draft";

    static readonly DATA_TEST_ASSESSMENT_SELF_ASSESSMENT_MARK = "assessment-feedback-self-assessment-mark";

    static readonly DATA_TEST_RUBRIC_FINAL_SUMMARY = "rubric-final-summary";

    static readonly DATA_TEST_RUBRIC_SUM = "rubric-sum";

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
     * @param participantId - The ID of the participant to set the grade for
     */
    async setGrade(grade: string | number, participantId: string | number): Promise<void> {
        await this.page
            .getByTestId(`${FeedbackForm.DATA_TEST_ASSESSMENT_FEEDBACK_MARK_INPUT}-${participantId}`)
            .fill(grade.toString());
    }

    /**
     * Selects a grade from a dropdown in the feedback form
     * @param grade - The grade to select
     * @param participantId - The ID of the participant to set the grade for
     */
    async setGradeDropdown(grade: string | number, participantId: string | number): Promise<void> {
        const gradeDropdown = this.page.getByTestId(
            `${FeedbackForm.DATA_TEST_ASSESSMENT_FEEDBACK_MARK_SELECT}-${participantId}`,
        );
        await gradeDropdown.selectOption(grade.toString());
    }

    /**
     * Selects Absent, Not Assessed, etc from the grade dropdown
     * @param option - The option to select
     * @param participantId - The ID of the participant to set the grade for
     */
    async setSpecialGradeDropdown(option: string, participantId: string | number): Promise<void> {
        const gradeDropdown = this.page.locator(`form[data-feedback-form="${participantId}"]`);
        await gradeDropdown.locator("div.combobox_arrow").click();
        await gradeDropdown.locator("li", { hasText: option }).click();
    }

    /**
     * Ensure the current grade in the feedback form matches the expected grade
     * @param value - The expected grade
     * @param participantId - The ID of the participant to check the grade for
     */
    async expectGrade(value: string | number, participantId: string | number): Promise<void> {
        const gradeInput = this.page.getByTestId(
            `${FeedbackForm.DATA_TEST_ASSESSMENT_FEEDBACK_MARK_INPUT}-${participantId}`,
        );
        await expect(gradeInput).toHaveValue(value.toString());
    }

    /**
     * Ensure the current grade in the feedback form matches the expected grade
     * @param value - The expected grade
     * @param participantId - The ID of the participant to check the grade for
     */
    async expectGradeDropdown(value: string | number, participantId: string | number): Promise<void> {
        const gradeDropdown = this.page.getByTestId(
            `${FeedbackForm.DATA_TEST_ASSESSMENT_FEEDBACK_MARK_SELECT}-${participantId}`,
        );
        await expect(gradeDropdown).toHaveValue(value.toString());
    }

    /**
     * Ensure there is no grade dropdown in the feedback form
     * @param participantId - The ID of the participant to check the grade for
     */
    async expectNoGradeSelector(participantId: string | number): Promise<void> {
        const gradeInput = this.page.getByTestId(
            `${FeedbackForm.DATA_TEST_ASSESSMENT_FEEDBACK_MARK_INPUT}-${participantId}`,
        );
        const gradeDropdown = this.page.getByTestId(
            `${FeedbackForm.DATA_TEST_ASSESSMENT_FEEDBACK_MARK_SELECT}-${participantId}`,
        );
        await expect(gradeInput).not.toBeAttached();
        await expect(gradeDropdown).not.toBeAttached();
    }

    /**
     * Sets comment in the feedback form
     * @param comment - The comment to set
     * @param participantId - The ID of the participant to set the comment for
     */
    async setComment(comment: string, participantId: string | number): Promise<void> {
        const commentEditor = this.page.getByTestId(
            `${FeedbackForm.DATA_TEST_ASSESSMENT_FEEDBACK_COMMENT_TEXTAREA}-${participantId}`,
        );
        await this.tester.fillCKEditorByLocator(commentEditor, comment);
    }

    /**
     * Sets attachment in the feedback form
     * @param db - The DatabaseHelper instance
     * @param filePath - The file to attach
     */
    async setAttachment(db: DatabaseHelper, filePath: string): Promise<void> {
        const attachZone = this.page.locator(FeedbackForm.SELECTOR_ATTACHMENT_ZONE);
        await this.tester.dragAndDropFile(db, attachZone, filePath);
    }

    /**
     * Selects a given rubric indicator level based on its value in the feedback form
     * @param indicatorId - The ID of the indicator
     * @param value - The value of the indicator
     */
    async selectRubricIndicatorByValue(indicatorId: number, value: string | number): Promise<void> {
        const inputId = await this.page
            .locator(`input[data-rubric-indicator="${indicatorId}"][data-value="${value}"]`)
            .getAttribute("id");
        await this.page.locator(`label[for="${inputId}"]`).click();
    }

    /**
     * Ensure the value of the rubric final summary bar matches the expected value
     * @param value - The expected value
     */
    async expectRubricFinalSummaryMark(value: string): Promise<void> {
        const finalSummaryBar = this.page.getByTestId(FeedbackForm.DATA_TEST_RUBRIC_FINAL_SUMMARY);
        await expect(finalSummaryBar).toHaveText(value);
    }

    /**
     * Expect that no rubric final summary bar is present
     */
    async expectNoRubricFinalSummary(): Promise<void> {
        const finalSummaryBar = this.page.getByTestId(FeedbackForm.DATA_TEST_RUBRIC_FINAL_SUMMARY);
        await expect(finalSummaryBar).not.toBeVisible();
    }

    /**
     * Expect the rubric "Assign XX from Rubric" button to contain the expected sum (value of XX)
     * @param value - The expected value
     */
    async expectRubricSum(value: string): Promise<void> {
        const rubricSum = this.page.getByTestId(FeedbackForm.DATA_TEST_RUBRIC_SUM);
        await expect(rubricSum).toHaveText(value);
    }

    /**
     * Expect that no rubric sum is present
     */
    async expectNoRubricSum(): Promise<void> {
        const rubricSum = this.page.getByTestId(FeedbackForm.DATA_TEST_RUBRIC_SUM);
        await expect(rubricSum).not.toBeVisible();
    }

    /**
     * Ensure the value of the self-assessment mark matches the expected value
     * @param value - The expected value
     */
    async expectSelfAssessmentMark(value: string): Promise<void> {
        const selfAssessmentMark = this.page.getByTestId(FeedbackForm.DATA_TEST_ASSESSMENT_SELF_ASSESSMENT_MARK);
        await expect(selfAssessmentMark).toHaveText(value);
    }

    /**
     * Expect that no self-assessment mark is present
     */
    async expectNoSelfAssessmentMark(): Promise<void> {
        const selfAssessmentMark = this.page.getByTestId(FeedbackForm.DATA_TEST_ASSESSMENT_SELF_ASSESSMENT_MARK);
        await expect(selfAssessmentMark).not.toBeVisible();
    }

    /**
     * Publishes the feedback
     */
    async publish(): Promise<void> {
        await this.page.getByTestId(FeedbackForm.DATA_TEST_ASSESSMENT_RESPONSE_INFO_SAVED_AS_DRAFT).waitFor();
        await this.page.waitForTimeout(2000); // Wait for auto-saving to finish
        await this.page.getByTestId(FeedbackForm.DATA_TEST_ASSESSMENT_FEEDBACK_PUBLISH_BUTTON).click();
        await this.page.waitForTimeout(1000); // Wait for the feedback to be published
    }

    /**
     * @returns The participant ID for the current feedback form
     */
    async getParticipantId(): Promise<number> {
        const participantId = await this.page.locator("[data-feedback-form]").getAttribute("data-feedback-form");
        if (participantId === null) {
            expect(participantId).not.toBeNull();
            throw new Error("participantId is null");
        }
        return Number(participantId);
    }
}
