import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class StatusPage {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/learning/assessments/{assessment_id}/status";

    static readonly SELECTOR_MARK = "input[name='mark']";

    static readonly SELECTOR_COMMENT = "textarea[name='comment']";

    static readonly SELECTOR_COMMENT_QUIZ_REVIEW = "textarea[id='final-review-comment-schoolbox-student']";

    static readonly SELECTOR_BUTTON_PUBLISH = "a[data-draft='publish']";

    static readonly DATA_TEST_JELLY_TOT = "sbx-pop-out-header-options-button";

    static readonly DATA_TEST_PARTICIPANT_JELLY_TOT = "sbx-pop-out-feedback-options-"; // Append "-[participantId]-button"

    static readonly DATA_TEST_PARTICIPANT_JELLY_TOT_VIEW_AND_GRADE = "assessment-status-feedback-viewSubmission-"; // Append "-[participantId]"

    static readonly DATA_TEST_PARTICIPANT_JELLY_TOT_RESPONSE_HISTORY = "assessment-status-feedback-responseHistory-"; // Append "-[participantId]"

    static readonly SELECTOR_FEEDBACK_IMPORT_MODAL = "#import-feedback-modal";

    static readonly SELECTOR_FEEDBACK_IMPORT_TEXTAREA = "#importFeedbackInput";

    static readonly SELECTOR_FEEDBACK_IMPORT_MARK_AND_COMMENT_RADIO = 'label[for="importFeedbackMarkAndComment"]';

    static readonly SELECTOR_FEEDBACK_MODAL_IMPORT_BUTTON = `a.submit:has-text("Import")`;

    static readonly SELECTOR_GRADE_INPUT = "assessment-feedback-mark-input-"; // Append "-[studentId]"

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Go to the status page for a given assessment
     * @param assessmentId - The assessment ID
     */
    async goto(assessmentId: number): Promise<void> {
        const targetUrl = StatusPage.URL.replace("{assessment_id}", assessmentId.toString());
        await this.page.goto(targetUrl);
    }

    /**
     * Gets selector for a given student
     * @param studentId - The student ID
     * @param fieldSelector - The field selector
     * @returns - The generated selector
     */
    static getStatusSelector(studentId: number, fieldSelector: string): string {
        return `[data-assessment-status] li[data-participant="${studentId}"] ${fieldSelector}`;
    }

    /**
     * Publishes feedback for the student on status page
     * @param studentId - The student ID
     * @param grade - The grade to give
     * @param comment - The comment to leave
     */
    async gradeSubmission(studentId: number, grade: number, comment: string): Promise<void> {
        await this.page
            .locator(StatusPage.getStatusSelector(studentId, StatusPage.SELECTOR_MARK))
            .fill(grade.toString());

        const commentEditor = this.page.locator(StatusPage.getStatusSelector(studentId, StatusPage.SELECTOR_COMMENT));
        await this.tester.fillCKEditorByLocator(commentEditor, comment);

        const publishButton = this.page.locator(
            StatusPage.getStatusSelector(studentId, StatusPage.SELECTOR_BUTTON_PUBLISH),
        );
        await expect(publishButton).toBeVisible();
        await this.page.waitForTimeout(2000); // Wait for auto-save to finish
        await publishButton.click();
        await expect(publishButton).not.toBeVisible();
    }

    /**
     * click the jelly tot
     */
    async clickJellyTot(): Promise<void> {
        await expect(this.page.getByTestId(StatusPage.DATA_TEST_JELLY_TOT)).toBeVisible();
        await this.page.getByTestId(StatusPage.DATA_TEST_JELLY_TOT).click();
    }

    /**
     * click an option in the jelly tot dropdown
     * @param option - The option to click: modify, feedback, task, download
     */
    async clickJellyTotOption(option: string): Promise<void> {
        const optionLink = this.page.locator(`#header-options a:has(sbx-icon[name="${option}"])`);
        await expect(optionLink).toBeVisible();
        await optionLink.click();
        await this.tester.common.waitForPageLoad();
    }

    /**
     * click the jelly tot for a participant
     * @param participantId - The ID of the participant
     */
    async clickParticipantJellyTot(participantId: number): Promise<void> {
        await this.page.getByTestId(`${StatusPage.DATA_TEST_PARTICIPANT_JELLY_TOT}${participantId}-button`).click();
    }

    /**
     * Click "View and grade submission" for a participant in the jelly tot
     * @param participantId - The ID of the participant
     */
    async clickViewAndGradeSubmission(participantId: number): Promise<void> {
        await this.page
            .getByTestId(`${StatusPage.DATA_TEST_PARTICIPANT_JELLY_TOT_VIEW_AND_GRADE}${participantId}`)
            .click();
    }

    /**
     * Click "Submission history" for a participant in the jelly tot
     * @param participantId - The ID of the participant
     */
    async clickSubmissionHistory(participantId: number): Promise<void> {
        await this.page
            .getByTestId(`${StatusPage.DATA_TEST_PARTICIPANT_JELLY_TOT_RESPONSE_HISTORY}${participantId}`)
            .click();
    }

    /**
     * enter a comment in the quiz review vertical
     * @param comment - overeall comment for the quiz review
     */
    async verticalQuizReviewComment(comment: string): Promise<void> {
        const commentEditor = this.page.locator(StatusPage.SELECTOR_COMMENT_QUIZ_REVIEW);
        await this.tester.fillCKEditorByLocator(commentEditor, comment);
    }

    /**
     * Type in the feedback import modal textarea
     * @param feedbackText - The feedback text to enter
     */
    async enterFeedbackImportModalText(feedbackText: string): Promise<void> {
        await this.page.locator(StatusPage.SELECTOR_FEEDBACK_IMPORT_TEXTAREA).fill(feedbackText);
    }

    /**
     * Tick on the "Import mark and comment" radio button in the feedback import modal
     */
    async clickImportMarkAndCommentRadio(): Promise<void> {
        await this.page.locator(StatusPage.SELECTOR_FEEDBACK_IMPORT_MARK_AND_COMMENT_RADIO).click();
    }

    /**
     * Click the "Import" button in the feedback import modal
     */
    async clickImportButtonInModal(): Promise<void> {
        await this.page.waitForTimeout(2000); // wait for 2000ms to ensure the data is processed before importing it
        await this.page.locator(StatusPage.SELECTOR_FEEDBACK_MODAL_IMPORT_BUTTON).click();
    }

    /**
     * Verify the grade input value for a given student
     * @param studentId - The student ID
     * @param expectedGrade - The expected grade value
     */
    async verifyGradeInputForStudent(studentId: number, expectedGrade: string): Promise<void> {
        const studentRow = this.page.getByTestId(`${StatusPage.SELECTOR_GRADE_INPUT}${studentId}`);
        const gradeInput = await studentRow.getAttribute("value");
        expect(gradeInput).toBe(expectedGrade);
    }

    /**
     * Verify the comment value for a given student
     * @param studentName - The student name
     * @param expectedComment - The expected comment value
     */
    async verifyCommentForStudent(studentName: string, expectedComment: string): Promise<void> {
        const commentLocator = this.page.locator(`p[data-placeholder="Feedback for ${studentName}"]`);
        await expect(commentLocator).toHaveText(expectedComment);
    }
}
