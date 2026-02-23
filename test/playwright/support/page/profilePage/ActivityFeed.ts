import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page, Locator } from "@playwright/test";
import { expect } from "@playwright/test";
import ProfilePage from "./ProfilePage";

export default class ActivityFeed {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly SELECTOR_FEED_ITEM = ".activity-feed li.feed-item";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Find an activity feed item by its title/content
     * @param text - The text to search for
     * @returns - The locator for the activity feed item
     */
    locateActivityFeedItem(text: string): Locator {
        return this.page.locator(ActivityFeed.SELECTOR_FEED_ITEM).filter({ hasText: text });
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
}
