/**
 * Utility class for a user's notification settings page.
 */

import { expect, type Locator, type Page } from "@playwright/test";

export default class NotificationSettings {
    private page: Page;

    static readonly URL = "/settings/notifications";

    static readonly DATA_TEST_SOURCE = "notification-settings-source-";

    static readonly DATA_TEST_DELIVERY_INPUT = "notification-delivery-input-";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Navigates to the user's notification settings page.
     * @returns A promise that resolves after navigation completes.
     */
    async goto(): Promise<void> {
        await this.page.goto(NotificationSettings.URL);
    }

    /**
     * Locates a delivery option for a notification source.
     * @param namespace - The notification namespace slug.
     * @param source - The notification source key.
     * @param delivery - The delivery option label.
     * @returns The delivery option input locator.
     */
    locateDelivery(namespace: string, source: string, delivery: string): Locator {
        const sourceContainer = this.page.getByTestId(`${NotificationSettings.DATA_TEST_SOURCE}${namespace}-${source}`);
        return sourceContainer.getByTestId(
            new RegExp(
                `^${NotificationSettings.DATA_TEST_DELIVERY_INPUT}${namespace}-${source}-\\d+-${delivery.toLowerCase()}$`,
            ),
        );
    }

    /**
     * Verifies the selected delivery option for a notification source.
     * @param namespace - The notification namespace slug.
     * @param source - The notification source key.
     * @param delivery - The expected delivery option label.
     * @returns A promise that resolves after the assertion completes.
     */
    async expectDelivery(namespace: string, source: string, delivery: string): Promise<void> {
        await expect(this.locateDelivery(namespace, source, delivery)).toBeChecked();
    }
}
