import { Page, expect } from "@playwright/test";
import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class LearningMomentsPage {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/search/user/{student_id}";

    // Constants for data-test selectors
    static readonly SHARE_LEARNING_MOMENTS_LINK = "learning-moment-evidence-share";

    static readonly TAKE_A_PHOTO_BUTTON = "learning-moment-evidence-share-takeAPhoto";

    static readonly TAKE_WRITE_SOMETHING = "learning-moment-evidence-share-writeSomething";

    static readonly TAKE_A_PHOTO_BUTTON_EVIDENCE =
        "button.vjs-camera-button.vjs-control.vjs-button.vjs-icon-photo-camera";

    static readonly DELETE_A_PHOTO = "videojs-record-delete-button";

    static readonly DELETE_A_PHOTO_CONFIRM_BUTTON = "videojs-record-delete-button-confirm";

    static readonly SUBMIT_BUTTON = "learning-moment-evidence-form-submit";

    static readonly COMMENT_DELETE_BUTTON = "learning-moment-evidence-delete-comment";

    static readonly COMMENT_CONFIRM_DELETE_BUTTON = "learning-moment-evidence-delete-comment-confirm";

    static readonly COMMENT_THREAD_COMMENT = "learning-moment-evidence-comment-thread-comment";

    static readonly LEARNING_MOMENT_DELETE_BUTTON = "learning-moment-evidence-delete"; // Append "-[evidenceId]"

    static readonly LEARNING_MOMENT_DELETE_BUTTON_CONFIRM = "learning-moment-evidence-delete-confirm";

    static readonly LEARNING_POST_COMMENT = "ckeditor-add-comment-input-submit-for-comment"; // Append "-[commentId]"

    static readonly EVIDENCE_POPOUT_BUTTON = "sbx-pop-out-evidence-{evidenceId}-dropdown-button";

    static readonly EVIDENCE_POPOUT_OPTION_MODIFY = "learning-moment-evidence-popout-option-modify"; // Append "-[evidenceId]"

    static readonly LEARNING_POST_COMMENT_TEXTAREA = "ckeditor-add-comment-textarea"; // Append "-[commentId]"

    static readonly EVIDENCE_MEDIA_DESCRIPTION_TEXTAREA = "learning-moment-evidence-media-description-textarea";

    static readonly EVIDENCE_BODY = "learning-moment-evidence-body";

    static readonly EVIDENCE_MEDIA = "learning-moment-evidence-media";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Gets url to create a learning moment for a student
     * @param { number } studentId - The student ID
     * @returns { string } - the URL
     */
    public static getStudentProfilePage(studentId: number): string {
        return this.URL.replace("{student_id}", studentId.toString());
    }

    /**
     * Click "Share a learning moment" link in the student profile page
     */
    async clickShareLearningMomentLink() {
        await this.page.getByTestId(LearningMomentsPage.SHARE_LEARNING_MOMENTS_LINK).click();
    }

    /**
     * Verify the "Share a learning moment" link exists in the student profile page
     */
    async verifyShareLearningMomentLinkExists() {
        await expect(this.page.getByTestId(LearningMomentsPage.SHARE_LEARNING_MOMENTS_LINK)).toBeVisible();
    }

    /**
     * Click "Take a photo" button on the modal
     */
    async clickTakeAPhoto() {
        await this.page.getByTestId(LearningMomentsPage.TAKE_A_PHOTO_BUTTON).click();
    }

    /**
     * Click "Write something" button on the modal
     */
    async clickWriteSomething() {
        await this.page.getByTestId(LearningMomentsPage.TAKE_WRITE_SOMETHING).click();
    }

    /**
     * Click "Take a photo" button on the create evidence page
     */
    async clickTakeAPhotoEvidence() {
        await this.page.locator(LearningMomentsPage.TAKE_A_PHOTO_BUTTON_EVIDENCE).click();
    }

    /**
     * Click "delete button to remove a photo already taken
     */
    async deletePhotoEvidence() {
        await this.page.getByTestId(LearningMomentsPage.DELETE_A_PHOTO).click();
    }

    /**
     * Click "delete button to remove a photo already taken
     */
    async confirmDeletePhoto() {
        await this.page.getByTestId(LearningMomentsPage.DELETE_A_PHOTO_CONFIRM_BUTTON).click();
    }

    /**
     * Click evidence submit button
     */
    async clickSubmit() {
        await this.page.getByTestId(LearningMomentsPage.SUBMIT_BUTTON).click();
    }

    /**
     * Typing a comment
     */
    async typeComment() {
        const commentTextarea = this.page.getByTestId(`${LearningMomentsPage.LEARNING_POST_COMMENT_TEXTAREA}-0`);
        await this.tester.fillCKEditorByLocator(
            commentTextarea,
            "Well done. This is my first comment in this learning moment!",
        );
    }

    /**
     * Verify a specific comment exists
     * @param { string } comment - The comment to verify
     */
    async verifyComment(comment: string) {
        await expect(this.page.getByTestId(LearningMomentsPage.COMMENT_THREAD_COMMENT)).toHaveText(comment);
    }

    /**
     * Verifies the quantity of comments visible in the page
     * @param { number } expectedCount - The expected number of comments
     */
    async verifyCommentCount(expectedCount: number) {
        await expect(this.page.getByTestId(LearningMomentsPage.COMMENT_THREAD_COMMENT)).toHaveCount(expectedCount);
    }

    /**
     * Typing a description for the media evidence
     * @param { string } description - The description to type
     */
    async typeMediaEvidenceDescription(description: string) {
        const descriptionTextarea = this.page.getByTestId(LearningMomentsPage.EVIDENCE_MEDIA_DESCRIPTION_TEXTAREA);
        await this.tester.fillCKEditorByLocator(descriptionTextarea, description);
    }

    /**
     * Click the 3-dot dropdown menu by passing its evidence
     * @param { number } evidenceId - The evidence ID
     */
    async clickDropdownMenu(evidenceId: number) {
        await this.page
            .getByTestId(LearningMomentsPage.EVIDENCE_POPOUT_BUTTON.replace("{evidenceId}", evidenceId.toString()))
            .click();
    }

    /**
     * Click Modify submenu
     * @param { number } evidenceId - The evidence ID
     */
    async clickModifySubmenu(evidenceId: number) {
        await this.page.getByTestId(`${LearningMomentsPage.EVIDENCE_POPOUT_OPTION_MODIFY}-${evidenceId}`).click();
    }

    /**
     * Click learning moments comment Delete  button
     */
    async clickCommentDeleteButton() {
        await this.page.getByTestId(LearningMomentsPage.COMMENT_DELETE_BUTTON).click();
    }

    /**
     * Click learning moments confirm Delete button on modal dialog
     */
    async clickConfirmDeleteButton() {
        await this.page.getByTestId(LearningMomentsPage.COMMENT_CONFIRM_DELETE_BUTTON).click();
    }

    /**
     * Click learning moment Delete button in the 3-dot menu
     * @param { number } evidenceId - The evidence ID
     */
    async clickLearningMomentDeleteButton(evidenceId: number) {
        await this.page.getByTestId(`${LearningMomentsPage.LEARNING_MOMENT_DELETE_BUTTON}-${evidenceId}`).click();
    }

    /**
     * Click learning moment confirm Delete button on modal dialog
     */
    async clickLearningMomentConfirmDeleteButton() {
        await this.page.getByTestId(LearningMomentsPage.LEARNING_MOMENT_DELETE_BUTTON_CONFIRM).click();
    }

    /**
     * Click post comment button
     */
    async clickPostComment() {
        await this.page.getByTestId(`${LearningMomentsPage.LEARNING_POST_COMMENT}-0`).click();
    }

    /**
     * Verify the evidence for a learning moment
     * @param { string } body - The expected body of the evidence
     */
    async verifyEvidence(body: string) {
        await expect(this.page.getByTestId(LearningMomentsPage.EVIDENCE_BODY).first()).toHaveText(body);
    }

    /**
     * Verifies that the evidence for a learning moment is not present
     * @param { string } body - The expected body of the evidence that was deleted
     */
    async verifyEvidenceNotPresent(body: string) {
        await expect(this.page.getByTestId(LearningMomentsPage.EVIDENCE_BODY).first()).not.toHaveText(body);
    }

    /**
     * Verify a learning moment has an attached photo
     */
    async verifyEvidenceHasPhoto() {
        const mediaLocator = this.page.getByTestId(LearningMomentsPage.EVIDENCE_MEDIA);
        await expect(mediaLocator).toBeVisible();
        await expect(mediaLocator).toHaveAttribute("alt", "photo.jpeg");
    }
}
