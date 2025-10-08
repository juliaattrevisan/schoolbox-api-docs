import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page, Locator } from "@playwright/test";
import { expect } from "@playwright/test";

export default class ProfilePage {
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
        return this.page.locator(ProfilePage.SELECTOR_FEED_ITEM).filter({ hasText: text });
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
}
