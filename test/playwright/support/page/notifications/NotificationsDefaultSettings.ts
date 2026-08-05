/**
 * Utility class for the notification default settings page.
 */

import { expect, type Locator, type Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export const NOTIFICATION_DELIVERY_OPTIONS = ["Muted", "Digest", "Instant", "Device", "Email"] as const;

type NotificationDeliveryOption = (typeof NOTIFICATION_DELIVERY_OPTIONS)[number];

export default class NotificationsDefaultSettings {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/adminv2/notifications/configure";

    static readonly DATA_TEST_HELP_BUTTON = "notifications-configure-help-button";

    static readonly DATA_TEST_DELIVERY_OPTIONS_MODAL = "notifications-delivery-options-modal";

    static readonly DATA_TEST_DELIVERY_OPTIONS_TITLE = "notifications-delivery-options-title";

    static readonly DATA_TEST_DELIVERY_OPTION = "notifications-delivery-option-";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to the global notification settings page.
     * @returns A promise that resolves after navigation completes.
     */
    async goto(): Promise<void> {
        await this.page.goto(NotificationsDefaultSettings.URL);
    }

    /**
     * Returns the notification delivery options modal.
     * @returns The notification delivery options modal locator.
     */
    locateDeliveryOptionsModal(): Locator {
        return this.page.getByTestId(NotificationsDefaultSettings.DATA_TEST_DELIVERY_OPTIONS_MODAL);
    }

    /**
     * Opens the notification delivery options help modal.
     * @returns A promise that resolves when the modal is fully open.
     */
    async openDeliveryOptions(): Promise<void> {
        await this.page.getByTestId(NotificationsDefaultSettings.DATA_TEST_HELP_BUTTON).click();
        await this.tester.common.waitForModalToBeOpen();
    }

    /**
     * Verifies that the notification delivery options modal and title are visible.
     * @returns A promise that resolves when the modal assertions complete.
     */
    async expectDeliveryOptionsModal(): Promise<void> {
        const modal = this.locateDeliveryOptionsModal();
        await expect(modal).toBeVisible();
        await expect(modal.getByTestId(NotificationsDefaultSettings.DATA_TEST_DELIVERY_OPTIONS_TITLE)).toHaveText(
            "Notification Delivery Options",
        );
    }

    /**
     * Verifies that a notification delivery option is presented in the help modal.
     * @param option - The notification delivery option to check.
     * @returns A promise that resolves when the option assertion completes.
     */
    async expectDeliveryOption(option: NotificationDeliveryOption): Promise<void> {
        const optionDataTest = `${NotificationsDefaultSettings.DATA_TEST_DELIVERY_OPTION}${option.toLowerCase()}`;
        await expect(this.locateDeliveryOptionsModal().getByTestId(optionDataTest)).toContainText(option);
    }
}
