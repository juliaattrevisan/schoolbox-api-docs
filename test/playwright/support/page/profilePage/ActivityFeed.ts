import type { Page, Locator } from "@playwright/test";
import { expect } from "@playwright/test";
import { EvidenceStatus } from "@schoolboxLearningMomentEvidence/evidenceTypes";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import LearningMomentShare from "@schoolboxTest/playwright/support/page/learningMoments/LearningMomentShare";
import ProfilePage from "./ProfilePage";

export default class ActivityFeed {
    private tester: AcceptanceTester;

    private page: Page;

    public readonly shareLearningModal: LearningMomentShare;

    // Constants for data-test selectors
    static readonly DATA_TEST_FEED_ITEM = "activity-feed-item"; // Append "-[discriminator]-[objectId]"

    // Learning Moment related selectors
    static readonly DATA_TEST_LEARNING_MOMENT_STATUS = "learning-moment-status";
    static readonly DATA_TEST_LEARNING_MOMENT_BODY = "learning-moment-body";
    static readonly DATA_TEST_LEARNING_MOMENT_EVIDENCE_MEDIA = "learning-moment-evidence-media";

    // Learning Moment action buttons
    static readonly DATA_TEST_LEARNING_MOMENT_ACTION_MODIFY = "learning-moment-action-modify";
    static readonly DATA_TEST_LEARNING_MOMENT_ACTION_SUBMIT = "learning-moment-action-submit";
    static readonly DATA_TEST_LEARNING_MOMENT_ACTION_PUBLISH = "learning-moment-action-publish";

    // Learning Moment Popout Menu
    static readonly DATA_TEST_LEARNING_MOMENT_POPOUT = "sbx-pop-out-learning-moment-{momentId}-popout-button"; // Replace {momentId}
    static readonly DATA_TEST_LEARNING_MOMENT_POPOUT_OPTION_MODIFY = "learning-moment-popout-option-modify"; // Append "-[momentId]"
    static readonly DATA_TEST_LEARNING_MOMENT_POPOUT_OPTION_DELETE = "learning-moment-delete"; // Append "-[evidenceId]"
    static readonly DATA_TEST_LEARNING_MOMENT_DELETE_CONFIRM = "learning-moment-delete-confirm";

    // Learning Moment Comment selectors
    static readonly DATA_TEST_LEARNING_MOMENT_COMMENT_DELETE_BUTTON = "learning-moment-delete-comment";
    static readonly DATA_TEST_LEARNING_MOMENT_COMMENT_CONFIRM_DELETE_BUTTON = "learning-moment-delete-comment-confirm";
    static readonly DATA_TEST_LEARNING_MOMENT_COMMENT_THREAD_COMMENT = "learning-moment-comment-thread-comment";
    static readonly DATA_TEST_LEARNING_POST_COMMENT = "ckeditor-add-comment-input-submit-for-comment"; // Append "-[commentId]"
    static readonly DATA_TEST_LEARNING_POST_COMMENT_TEXTAREA = "ckeditor-add-comment-textarea"; // Append "-[commentId]"

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
        this.shareLearningModal = new LearningMomentShare(tester, page);
    }

    /**
     * Find an activity feed item by its title/content
     * @param text - The text to search for
     * @returns - The locator for the activity feed item
     */
    locateActivityFeedItem(text: string): Locator {
        const anyActivityFeedItem = new RegExp(`^${ActivityFeed.DATA_TEST_FEED_ITEM}`);
        return this.page.getByTestId(anyActivityFeedItem).filter({ hasText: text });
    }

    /**
     * Verify the mark for an item in the activity feed
     * @param itemText - The text to use to locate the item
     * @param mark - The expected mark (or null if no mark is expected)
     */
    async expectActivityFeedItemMark(itemText: string, mark: string | null): Promise<void> {
        const item = this.locateActivityFeedItem(itemText);
        const markLocator = item.locator(".grade");
        if (mark === null) {
            await expect(markLocator).not.toBeVisible();
            return;
        }
        await expect(markLocator).toHaveText(mark);
    }

    /**
     * Click the "Share a learning moment" button in the activity feed profile tab
     */
    async clickShareLearningMoment(): Promise<void> {
        await this.page.getByTestId(ProfilePage.DATA_TEST_SHARE_LEARNING_MOMENT_BUTTON).click();
    }

    /**
     * Verify the "Share a learning moment" button exists in the activity feed profile tab
     */
    async verifyShareLearningMomentButtonExists(): Promise<void> {
        await expect(this.page.getByTestId(ProfilePage.DATA_TEST_SHARE_LEARNING_MOMENT_BUTTON)).toBeVisible();
    }

    /**
     * Verify the "Set Goals" button exists in the activity feed profile tab
     */
    async verifySetGoalsButtonExists(): Promise<void> {
        await expect(this.page.locator(ProfilePage.DATA_TEST_SET_GOALS_BUTTON)).toBeVisible();
    }

    /**
     * Verify the "Award Achievements" button exists in the activity feed profile tab
     */
    async verifyAwardAchievementsButtonExists(): Promise<void> {
        await expect(this.page.locator(ProfilePage.SELECTOR_AWARD_ACHIEVEMENTS_BUTTON)).toBeVisible();
    }

    /**
     * Verify if filters are visible on profile page
     * Learning Moments, Goals, Achievements, Feedback filters
     */
    async checkFiltersAreVisible(): Promise<void> {
        const learningMomentsFilter = this.page.locator(ProfilePage.SELECTOR_LEARNING_MOMENTS_FILTER);
        const goalsFilter = this.page.locator(ProfilePage.SELECTOR_GOALS_FILTER);
        const achievementsFilter = this.page.locator(ProfilePage.SELECTOR_ACHIEVEMENTS_FILTER);
        const feedbackFilter = this.page.locator(ProfilePage.SELECTOR_FEEDBACK_FILTER);

        await expect(learningMomentsFilter).toBeVisible();
        await expect(goalsFilter).toBeVisible();
        await expect(achievementsFilter).toBeVisible();
        await expect(feedbackFilter).toBeVisible();
    }

    /** LEARNING MOMENTS */

    /**
     * Find a particular learning moment in the activity feed based on the body
     * @param body - The expected body of the learning moment
     * @returns Locator
     */
    public locateMomentWithBody(body: string): Locator {
        const anyActivityFeedLearningMoment = new RegExp(`^${ActivityFeed.DATA_TEST_FEED_ITEM}-moment`);
        const matchingBodyLocator = this.page
            .getByTestId(ActivityFeed.DATA_TEST_LEARNING_MOMENT_BODY)
            .filter({ hasText: body });
        return this.page.getByTestId(anyActivityFeedLearningMoment).filter({ has: matchingBodyLocator });
    }

    /**
     * Retrieve the ID of a particular learning moment
     * @param moment - Locator for the learning moment
     * @returns The learning moment ID
     */
    public static async getMomentLocatorId(moment: Locator): Promise<number> {
        const testId = await moment.getAttribute("data-test-id");
        if (!testId) {
            throw new Error("Moment does not have a data-test-id attribute");
        }

        const match = testId.match(/moment-(\d+)/);
        if (!match) {
            throw new Error(`Could not extract moment ID from data-test-id: ${testId}`);
        }

        return parseInt(match[1], 10);
    }

    /**
     * Verify the text evidence (body) for a learning moment
     * TODO: This only verifies the most recent learning moment, we should avoid using .first() at some point
     * @param body - The expected body of the learning moment
     */
    async verifyMomentEvidence(body: string): Promise<void> {
        await expect(this.page.getByTestId(ActivityFeed.DATA_TEST_LEARNING_MOMENT_BODY).first()).toHaveText(body);
    }

    /**
     * Verifies that the text evidence (body) for a learning moment is not present
     * TODO: This only verifies the most recent learning moment, we should avoid using .first() at some point
     * @param body - The expected body of the learning moment that was deleted
     */
    async verifyMomentEvidenceNotPresent(body: string): Promise<void> {
        await expect(this.page.getByTestId(ActivityFeed.DATA_TEST_LEARNING_MOMENT_BODY).first()).not.toHaveText(body);
    }

    /**
     * Verify a learning moment has an attached photo
     * TODO: This assumes that only one learning moment has a photo attached
     */
    async verifyMomentEvidenceHasPhoto(): Promise<void> {
        const mediaLocator = this.page.getByTestId(ActivityFeed.DATA_TEST_LEARNING_MOMENT_EVIDENCE_MEDIA);
        await expect(mediaLocator).toBeVisible();
        await expect(mediaLocator).toHaveAttribute("alt", "photo.jpeg");
    }

    /**
     * Verify a learning moment is a particular status
     * @param moment - Locator to narrow down to a particular learning moment
     * @param status - The status to check
     */
    static async verifyMomentHasStatus(moment: Locator, status: EvidenceStatus): Promise<void> {
        // For published, no status is shown
        if (status === EvidenceStatus.PUBLISHED) {
            const anyStatusLocator = new RegExp(`^${ActivityFeed.DATA_TEST_LEARNING_MOMENT_STATUS}`);
            await expect(moment.getByTestId(anyStatusLocator)).not.toBeVisible();
            return;
        }

        await expect(moment.getByTestId(`${ActivityFeed.DATA_TEST_LEARNING_MOMENT_STATUS}-${status}`)).toBeVisible();
    }

    /**
     * Click the "Modify" action button for a learning moment
     * This is available if the moment is not published yet
     * @param moment - Locator to narrow down to a particular learning moment
     */
    static async clickMomentModifyAction(moment: Locator): Promise<void> {
        await moment.getByTestId(ActivityFeed.DATA_TEST_LEARNING_MOMENT_ACTION_MODIFY).click();
    }

    /**
     * Click the "Submit for approval" action button for a learning moment
     * @param moment - Locator to narrow down to a particular learning moment
     */
    static async clickMomentSubmitForApprovalAction(moment: Locator): Promise<void> {
        await moment.getByTestId(ActivityFeed.DATA_TEST_LEARNING_MOMENT_ACTION_SUBMIT).click();
    }

    /**
     * Click the "Publish" action button for a learning moment
     * @param moment - Locator to narrow down to a particular learning moment
     */
    static async clickMomentPublishAction(moment: Locator): Promise<void> {
        await moment.getByTestId(ActivityFeed.DATA_TEST_LEARNING_MOMENT_ACTION_PUBLISH).click();
    }

    /**
     * Click the 3-dot dropdown menu button for a particular learning moment
     * @param momentId - The learning moment ID
     */
    async clickMomentDropdownMenu(momentId: number): Promise<void> {
        const dropdownId = ActivityFeed.DATA_TEST_LEARNING_MOMENT_POPOUT.replace("{momentId}", momentId.toString());
        await this.page.getByTestId(dropdownId).click();
    }

    /**
     * Click the "Modify" option within the 3-dot menu for a particular learning moment
     * @param momentId - The learning moment ID
     */
    async clickMomentModifySubmenu(momentId: number): Promise<void> {
        await this.page
            .getByTestId(`${ActivityFeed.DATA_TEST_LEARNING_MOMENT_POPOUT_OPTION_MODIFY}-${momentId}`)
            .click();
    }

    /**
     * Click the Delete button within the 3-dot menu for a particular learning moment
     * @param momentId - The learning moment ID
     */
    async clickMomentDeleteButton(momentId: number): Promise<void> {
        await this.page
            .getByTestId(`${ActivityFeed.DATA_TEST_LEARNING_MOMENT_POPOUT_OPTION_DELETE}-${momentId}`)
            .click();
    }

    /**
     * Click learning moment confirm Delete button within the modal dialog
     */
    async clickMomentConfirmDeleteButton(): Promise<void> {
        await this.page.getByTestId(ActivityFeed.DATA_TEST_LEARNING_MOMENT_DELETE_CONFIRM).click();
    }

    /**
     * Verify the count of learning moments visible in the activity feed
     * @param expectedCount - The expected number of learning moments
     */
    async verifyMomentCount(expectedCount: number): Promise<void> {
        const anyActivityFeedLearningMoment = new RegExp(`^${ActivityFeed.DATA_TEST_FEED_ITEM}-moment`);
        await expect(this.page.getByTestId(anyActivityFeedLearningMoment)).toHaveCount(expectedCount);
    }

    /**
     * Typing a comment for a learning moment
     * TODO: This assumes that we're commenting on the most recent learning moment, we should avoid using -0 at some point
     * @param text - The comment to type in the comment textarea
     */
    async typeMomentComment(text: string): Promise<void> {
        const commentTextarea = this.page.getByTestId(`${ActivityFeed.DATA_TEST_LEARNING_POST_COMMENT_TEXTAREA}-0`);
        await this.tester.fillCKEditorByLocator(commentTextarea, text);
    }

    /**
     * Verify a specific comment exists
     * @param comment - The comment to verify
     */
    async verifyMomentComment(comment: string): Promise<void> {
        await expect(this.page.getByTestId(ActivityFeed.DATA_TEST_LEARNING_MOMENT_COMMENT_THREAD_COMMENT)).toHaveText(
            comment,
        );
    }

    /**
     * Verifies the quantity of comments visible in the page
     * @param expectedCount - The expected number of comments
     */
    async verifyMomentCommentCount(expectedCount: number): Promise<void> {
        const anyCommentThread = new RegExp(`${ActivityFeed.DATA_TEST_LEARNING_MOMENT_COMMENT_THREAD_COMMENT}-\\d+`);
        await expect(this.page.getByTestId(anyCommentThread)).toHaveCount(expectedCount);
    }

    /**
     * Click learning moments comment Delete  button
     */
    async clickMomentCommentDeleteButton(): Promise<void> {
        await this.page.getByTestId(ActivityFeed.DATA_TEST_LEARNING_MOMENT_COMMENT_DELETE_BUTTON).click();
    }

    /**
     * Click post comment button
     */
    async clickMomentPostComment(): Promise<void> {
        await this.page.getByTestId(`${ActivityFeed.DATA_TEST_LEARNING_POST_COMMENT}-0`).click();
    }

    /**
     * Click learning moment confirm comment Delete button within the modal dialog
     */
    async clickMomentConfirmCommentDeleteButton(): Promise<void> {
        await this.page.getByTestId(ActivityFeed.DATA_TEST_LEARNING_MOMENT_COMMENT_CONFIRM_DELETE_BUTTON).click();
    }
}
