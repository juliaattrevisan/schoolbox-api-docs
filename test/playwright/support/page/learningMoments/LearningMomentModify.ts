import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import LearningMomentShare from "./LearningMomentShare";

/**
 * Support file for the learning moment modify page
 */
export default class LearningMomentModify {
    private tester: AcceptanceTester;

    private page: Page;

    public readonly share: LearningMomentShare;

    // Constants for data-test selectors
    static readonly DATA_TEST_EVIDENCE_DELETE = "mf-evidence-delete";
    static readonly DATA_TEST_SAVE_DRAFT_BUTTON = "learning-moment-save-draft";
    static readonly DATA_TEST_SUBMIT_FOR_APPROVAL_BUTTON = "learning-moment-submit-for-approval";
    static readonly DATA_TEST_REQUEST_UPDATE_BUTTON = "learning-moment-request-update";
    static readonly DATA_TEST_REQUEST_UPDATE_REASON = "learning-moment-request-update-reason";
    static readonly DATA_TEST_REQUEST_UPDATE_CONFIRM = "learning-moment-request-update-confirm";
    static readonly DATA_TEST_PUBLISH_BUTTON = "learning-moment-publish";
    static readonly ID_LEARNING_MOMENT_BODY_TEXTAREA = "mf-evidence-textarea";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;

        this.share = new LearningMomentShare(tester, page);
    }

    /**
     * Delete the evidence present in the learning moment
     * TODO: This only works if there's 1 evidence attached, multiple will fail for now
     */
    async deleteEvidence(): Promise<void> {
        await this.page.getByTestId(LearningMomentModify.DATA_TEST_EVIDENCE_DELETE).click();
    }

    /**
     * Set the body of the learning moment (aka the "Write Something" evidence flow)
     * @param text - The body to set
     */
    async typeBody(text: string): Promise<void> {
        await this.tester.fillCKEditorById(LearningMomentModify.ID_LEARNING_MOMENT_BODY_TEXTAREA, text);
    }

    /**
     * Set the body of the learning moment to an editor template
     * @param templateTitle - The title of the template to use
     */
    async setBodyEditorTemplate(templateTitle: string): Promise<void> {
        await this.tester.clickEditorTemplateCKEditor(
            LearningMomentModify.ID_LEARNING_MOMENT_BODY_TEXTAREA,
            templateTitle,
        );
    }

    /**
     * Click the "Save for later" (Save draft) button
     */
    async clickSaveDraft(): Promise<void> {
        await this.page.getByTestId(LearningMomentModify.DATA_TEST_SAVE_DRAFT_BUTTON).click();
    }

    /**
     * Click the "Submit for approval" button
     */
    async clickSubmitForApproval(): Promise<void> {
        await this.page.getByTestId(LearningMomentModify.DATA_TEST_SUBMIT_FOR_APPROVAL_BUTTON).click();
    }

    /**
     * Click the "Request update" button (if the learning moment is rejected)
     */
    async clickRequestUpdate(): Promise<void> {
        await this.page.getByTestId(LearningMomentModify.DATA_TEST_REQUEST_UPDATE_BUTTON).click();
    }

    /**
     * Confirm the "Request update" action in the confirmation modal with a given reason
     * @param reason - The reason for requesting an update
     */
    async confirmRequestUpdate(reason: string): Promise<void> {
        await this.page.getByTestId(LearningMomentModify.DATA_TEST_REQUEST_UPDATE_REASON).fill(reason);
        await this.page.getByTestId(LearningMomentModify.DATA_TEST_REQUEST_UPDATE_CONFIRM).click();
    }

    /**
     * Publish the learning moment
     * If moderation is enabled, this could say "Approve" instead
     */
    async clickPublish(): Promise<void> {
        await this.page.getByTestId(LearningMomentModify.DATA_TEST_PUBLISH_BUTTON).click();
    }
}
