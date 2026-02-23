import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import LearningMomentsShare from "./LearningMomentsShare";

export default class LearningMomentsPage {
    private tester: AcceptanceTester;

    private page: Page;

    public readonly share: LearningMomentsShare;

    // Constants for data-test selectors
    static readonly DATA_TEST_EVIDENCE_DELETE = `mf-evidence-delete`;

    static readonly SUBMIT_BUTTON = "learning-moment-evidence-form-submit";

    static readonly APPROVE_BUTTON = `button[class="submit"]`;

    static readonly COMMENT_DELETE_BUTTON = "learning-moment-evidence-delete-comment";

    static readonly COMMENT_CONFIRM_DELETE_BUTTON = "learning-moment-evidence-delete-comment-confirm";

    static readonly COMMENT_THREAD_COMMENT = "learning-moment-evidence-comment-thread-comment";

    static readonly LEARNING_MOMENT_DELETE_BUTTON = "learning-moment-evidence-delete"; // Append "-[evidenceId]"

    static readonly LEARNING_MOMENT_DELETE_BUTTON_CONFIRM = "learning-moment-evidence-delete-confirm";

    static readonly LEARNING_POST_COMMENT = "ckeditor-add-comment-input-submit-for-comment"; // Append "-[commentId]"

    static readonly EVIDENCE_POPOUT_BUTTON = "sbx-pop-out-evidence-{evidenceId}-dropdown-button";

    static readonly EVIDENCE_POPOUT_OPTION_MODIFY = "learning-moment-evidence-popout-option-modify"; // Append "-[evidenceId]"

    static readonly LEARNING_POST_COMMENT_TEXTAREA = "ckeditor-add-comment-textarea"; // Append "-[commentId]"

    static readonly ID_LEARNING_MOMENT_BODY_TEXTAREA = "mf-evidence-textarea";

    static readonly WRITE_SOMETHING_BUTTON = "a.icon-textbox.share-text";

    static readonly CAMERA_BUTTON = "a.icon-camera.share-photo";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;

        this.share = new LearningMomentsShare(tester, page);
    }

    /**
     * Delete the evidence present in the learning moment
     * TODO: This only works if there's 1 evidence attached, multiple will fail for now
     */
    async deleteEvidence(): Promise<void> {
        await this.page.getByTestId(LearningMomentsPage.DATA_TEST_EVIDENCE_DELETE).click();
    }

    /**
     * Set the body of the learning moment (aka the "Write Something" evidence flow)
     * @param text - The body to set
     */
    async typeBody(text: string): Promise<void> {
        await this.tester.fillCKEditorById(LearningMomentsPage.ID_LEARNING_MOMENT_BODY_TEXTAREA, text);
    }

    /**
     * Set the body of the learning moment to an editor template
     * @param templateTitle - The title of the template to use
     */
    async setBodyEditorTemplate(templateTitle: string): Promise<void> {
        await this.tester.clickEditorTemplateCKEditor(
            LearningMomentsPage.ID_LEARNING_MOMENT_BODY_TEXTAREA,
            templateTitle,
        );
    }

    /**
     * Click evidence submit button
     */
    async clickSubmit(): Promise<void> {
        await this.page.locator(LearningMomentsPage.APPROVE_BUTTON).click();
    }

    /**
     * Typing a comment
     * @param text - The comment to type in the comment textarea
     */
    async typeComment(text: string): Promise<void> {
        const commentTextarea = this.page.getByTestId(`${LearningMomentsPage.LEARNING_POST_COMMENT_TEXTAREA}-0`);
        await this.tester.fillCKEditorByLocator(commentTextarea, text);
    }

    /**
     * Verify a specific comment exists
     * @param comment - The comment to verify
     */
    async verifyComment(comment: string): Promise<void> {
        await expect(this.page.getByTestId(LearningMomentsPage.COMMENT_THREAD_COMMENT)).toHaveText(comment);
    }

    /**
     * Verifies the quantity of comments visible in the page
     * @param expectedCount - The expected number of comments
     */
    async verifyCommentCount(expectedCount: number): Promise<void> {
        await expect(this.page.getByTestId(LearningMomentsPage.COMMENT_THREAD_COMMENT)).toHaveCount(expectedCount);
    }

    /**
     * Click the 3-dot dropdown menu by passing its evidence
     * @param evidenceId - The evidence ID
     */
    async clickDropdownMenu(evidenceId: number): Promise<void> {
        await this.page
            .getByTestId(LearningMomentsPage.EVIDENCE_POPOUT_BUTTON.replace("{evidenceId}", evidenceId.toString()))
            .click();
    }

    /**
     * Click Modify submenu
     * @param evidenceId - The evidence ID
     */
    async clickModifySubmenu(evidenceId: number): Promise<void> {
        await this.page.getByTestId(`${LearningMomentsPage.EVIDENCE_POPOUT_OPTION_MODIFY}-${evidenceId}`).click();
    }

    /**
     * Click learning moments comment Delete  button
     */
    async clickCommentDeleteButton(): Promise<void> {
        await this.page.getByTestId(LearningMomentsPage.COMMENT_DELETE_BUTTON).click();
    }

    /**
     * Click learning moments confirm Delete button on modal dialog
     */
    async clickConfirmDeleteButton(): Promise<void> {
        await this.page.getByTestId(LearningMomentsPage.COMMENT_CONFIRM_DELETE_BUTTON).click();
    }

    /**
     * Click learning moment Delete button in the 3-dot menu
     * @param evidenceId - The evidence ID
     */
    async clickLearningMomentDeleteButton(evidenceId: number): Promise<void> {
        await this.page.getByTestId(`${LearningMomentsPage.LEARNING_MOMENT_DELETE_BUTTON}-${evidenceId}`).click();
    }

    /**
     * Click learning moment confirm Delete button on modal dialog
     */
    async clickLearningMomentConfirmDeleteButton(): Promise<void> {
        await this.page.getByTestId(LearningMomentsPage.LEARNING_MOMENT_DELETE_BUTTON_CONFIRM).click();
    }

    /**
     * Click post comment button
     */
    async clickPostComment(): Promise<void> {
        await this.page.getByTestId(`${LearningMomentsPage.LEARNING_POST_COMMENT}-0`).click();
    }
}
