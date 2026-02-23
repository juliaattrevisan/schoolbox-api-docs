import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

/**
 * Support file for the share buttons located in:
 * - the "Share a Learning Moment" modal
 * - homepage component
 * - learning moment modify form
 */
export default class LearningMomentsShare {
    private tester: AcceptanceTester;

    private page: Page;

    // Constants for data-test selectors
    static readonly DATA_TEST_SHARE_PHOTO = "learning-moment-evidence-share-photo";
    static readonly DATA_TEST_SHARE_VIDEO = "learning-moment-evidence-share-video";
    static readonly DATA_TEST_SHARE_AUDIO = "learning-moment-evidence-share-audio";
    static readonly DATA_TEST_SHARE_FILE = "learning-moment-evidence-share-file";
    static readonly DATA_TEST_SHARE_TEXT = "learning-moment-evidence-share-text";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Click the "Take a photo" button
     */
    public async clickSharePhoto(): Promise<void> {
        await this.page.getByTestId(LearningMomentsShare.DATA_TEST_SHARE_PHOTO).click();
    }

    /**
     * Click the "Record a video" button
     */
    public async clickShareVideo(): Promise<void> {
        await this.page.getByTestId(LearningMomentsShare.DATA_TEST_SHARE_VIDEO).click();
    }

    /**
     * Click the "Record audio" button
     */
    public async clickShareAudio(): Promise<void> {
        await this.page.getByTestId(LearningMomentsShare.DATA_TEST_SHARE_AUDIO).click();
    }

    /**
     * Click the "Upload file" button
     */
    public async clickShareFile(): Promise<void> {
        await this.page.getByTestId(LearningMomentsShare.DATA_TEST_SHARE_FILE).click();
    }

    /**
     * Click the "Write something" button
     */
    public async clickShareText(): Promise<void> {
        await this.page.getByTestId(LearningMomentsShare.DATA_TEST_SHARE_TEXT).click();
    }
}
