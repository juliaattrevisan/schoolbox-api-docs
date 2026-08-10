/**
 * Utility class for the notification side panel.
 */

import { expect, type Locator, type Page } from "@playwright/test";

export default class NotificationSidePanel {
    private page: Page;

    static readonly DATA_TEST_TOGGLE = "notification-side-panel-toggle";

    static readonly DATA_TEST_PANEL = "notification-side-panel";

    static readonly DATA_TEST_NOTIFICATION_TITLE = "notification-side-panel-item-title";

    static readonly SELECTOR_FOOTER = "[data-msgfooter-action]";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Waits for the initial notification fetch to populate the side panel.
     * @returns A promise that resolves when the notification list has loaded.
     */
    private async waitForNotifications(): Promise<void> {
        await expect(this.page.locator(NotificationSidePanel.SELECTOR_FOOTER)).toHaveText(
            /^(See All|No Notifications)$/,
        );
    }

    /**
     * Opens the notification side panel.
     * @returns A promise that resolves when the panel is open.
     */
    async open(): Promise<void> {
        await this.page.getByTestId(NotificationSidePanel.DATA_TEST_TOGGLE).click();
        await expect(this.page.getByTestId(NotificationSidePanel.DATA_TEST_PANEL)).toBeVisible();
        await this.waitForNotifications();
    }

    /**
     * Locates a notification title in the side panel.
     * @param text - The title text to find.
     * @returns The matching notification title locator.
     */
    locateNotification(text: string): Locator {
        return this.page.getByTestId(NotificationSidePanel.DATA_TEST_NOTIFICATION_TITLE).filter({ hasText: text });
    }

    /**
     * Verifies that a notification is shown in the side panel.
     * @param text - The notification title text.
     * @returns A promise that resolves after the assertion completes.
     */
    async expectNotification(text: string): Promise<void> {
        await this.waitForNotifications();
        await expect(this.locateNotification(text).first()).toBeVisible({ timeout: 60000 });
    }

    /**
     * Verifies that a notification is absent from the side panel.
     * @param text - The notification title text.
     * @returns A promise that resolves after the assertion completes.
     */
    async expectNoNotification(text: string): Promise<void> {
        await this.waitForNotifications();
        await expect(this.locateNotification(text)).toHaveCount(0);
    }
}
