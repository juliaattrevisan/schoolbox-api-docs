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

    static readonly DATA_TEST_SOURCE = "notifications-configure-source-";

    static readonly DATA_TEST_SOURCE_TOGGLE = "notifications-configure-source-toggle";

    static readonly DATA_TEST_SOURCE_CONTENT = "notifications-configure-source-content";

    static readonly DATA_TEST_ROLE = "notifications-configure-role-";

    static readonly DATA_TEST_DELIVERY = "notification-delivery-";

    static readonly DATA_TEST_DELIVERY_INPUT = "notification-delivery-input-";

    static readonly DATA_TEST_NAMESPACE = "notifications-configure-namespace-";

    static readonly DATA_TEST_NAMESPACE_ACTIONS = "notifications-configure-actions-";

    static readonly DATA_TEST_APPLY_DEFAULT = "notifications-configure-apply-default-";

    static readonly DATA_TEST_APPLY_DEFAULT_MODAL = "notifications-configure-apply-default-modal";

    static readonly DATA_TEST_APPLY_DEFAULT_CONFIRM = "notifications-configure-apply-default-confirm";

    static readonly DATA_TEST_ADVANCED = "notifications-configure-advanced-";

    static readonly DATA_TEST_ADVANCED_MODAL = "notifications-configure-advanced-modal";

    static readonly DATA_TEST_ADVANCED_FORM = "notifications-configure-advanced-form-";

    static readonly DATA_TEST_ADVANCED_MASK = "notification-advanced-mask-";

    static readonly DATA_TEST_ADVANCED_MASK_INPUT = "notification-advanced-mask-input-";

    static readonly DATA_TEST_ADVANCED_SAVE = "notifications-configure-advanced-save";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to the global notification settings page.
     * @param roleType - An optional role-type filter.
     * @returns A promise that resolves after navigation completes.
     */
    async goto(roleType?: "student" | "staff" | "parent"): Promise<void> {
        const url = roleType ? `${NotificationsDefaultSettings.URL}/${roleType}` : NotificationsDefaultSettings.URL;
        await this.page.goto(url);
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

    /**
     * Sets the default delivery option for a role and notification source.
     * @param namespace - The notification namespace slug.
     * @param source - The notification source key.
     * @param roleName - The role whose default is being changed.
     * @param delivery - The delivery option to select.
     * @returns A promise that resolves after the setting is saved.
     */
    async setRoleDelivery(
        namespace: string,
        source: string,
        roleName: string,
        delivery: NotificationDeliveryOption,
    ): Promise<void> {
        const sourceContainer = this.page.getByTestId(
            `${NotificationsDefaultSettings.DATA_TEST_SOURCE}${namespace}-${source}`,
        );
        const sourceToggle = sourceContainer.getByTestId(NotificationsDefaultSettings.DATA_TEST_SOURCE_TOGGLE);
        const sourceContent = sourceContainer.getByTestId(NotificationsDefaultSettings.DATA_TEST_SOURCE_CONTENT);

        // Expand the source section if it's collapsed
        if (await sourceContent.isHidden()) {
            await sourceToggle.click();
        }

        // Locate the role and its delivery method control, and click it if it's not already selected.
        const role = sourceContainer
            .getByTestId(new RegExp(`^${NotificationsDefaultSettings.DATA_TEST_ROLE}${namespace}-${source}-`))
            .filter({ hasText: roleName });
        const deliveryControl = role.getByTestId(
            new RegExp(
                `^${NotificationsDefaultSettings.DATA_TEST_DELIVERY}${namespace}-${source}-\\d+-${delivery.toLowerCase()}$`,
            ),
        );
        const deliveryInput = role.getByTestId(
            new RegExp(
                `^${NotificationsDefaultSettings.DATA_TEST_DELIVERY_INPUT}${namespace}-${source}-\\d+-${delivery.toLowerCase()}$`,
            ),
        );

        if (await deliveryInput.isChecked()) {
            return;
        }

        // If the control is disabled, open the advanced settings modal to enable it first.
        if (await deliveryInput.isDisabled()) {
            await role
                .getByTestId(
                    new RegExp(`^${NotificationsDefaultSettings.DATA_TEST_ADVANCED}${namespace}-${source}-\\d+$`),
                )
                .click();
            await this.tester.common.waitForModalToBeOpen();

            const advancedModal = this.page.getByTestId(NotificationsDefaultSettings.DATA_TEST_ADVANCED_MODAL);
            const advancedForm = advancedModal.getByTestId(
                new RegExp(`^${NotificationsDefaultSettings.DATA_TEST_ADVANCED_FORM}${namespace}-${source}-\\d+$`),
            );
            const allowedDelivery = advancedForm.getByTestId(
                new RegExp(
                    `^${NotificationsDefaultSettings.DATA_TEST_ADVANCED_MASK}${namespace}-${source}-\\d+-${delivery.toLowerCase()}$`,
                ),
            );
            const allowedDeliveryInput = advancedForm.getByTestId(
                new RegExp(
                    `^${NotificationsDefaultSettings.DATA_TEST_ADVANCED_MASK_INPUT}${namespace}-${source}-\\d+-${delivery.toLowerCase()}$`,
                ),
            );
            if (!(await allowedDeliveryInput.isChecked())) {
                await allowedDelivery.click();
            }

            const defaultDelivery = advancedForm.getByTestId(
                new RegExp(
                    `^${NotificationsDefaultSettings.DATA_TEST_DELIVERY}${namespace}-${source}-\\d+-${delivery.toLowerCase()}$`,
                ),
            );
            const defaultDeliveryInput = advancedForm.getByTestId(
                new RegExp(
                    `^${NotificationsDefaultSettings.DATA_TEST_DELIVERY_INPUT}${namespace}-${source}-\\d+-${delivery.toLowerCase()}$`,
                ),
            );
            if (!(await defaultDeliveryInput.isChecked())) {
                await defaultDelivery.click();
            }

            const saveResponse = this.page.waitForResponse(
                (response) =>
                    response.request().method() === "POST" &&
                    new URL(response.url()).pathname === NotificationsDefaultSettings.URL,
            );
            await advancedForm.getByTestId(NotificationsDefaultSettings.DATA_TEST_ADVANCED_SAVE).click();
            expect((await saveResponse).ok()).toBeTruthy();
            await this.tester.common.waitForModalToBeClosed();
            await expect(deliveryInput).toBeEnabled();
            await expect(deliveryInput).toBeChecked();
            return;
        }

        const saveResponse = this.page.waitForResponse(
            (response) =>
                response.request().method() === "POST" &&
                new URL(response.url()).pathname.startsWith(NotificationsDefaultSettings.URL),
        );
        await deliveryControl.click();
        expect((await saveResponse).ok()).toBeTruthy();
    }

    /**
     * Applies a namespace's defaults to all existing users.
     * @param namespace - The notification namespace slug.
     * @returns A promise that resolves after the defaults are applied.
     */
    async applyDefaultsToAllUsers(namespace: string): Promise<void> {
        const namespaceActions = this.page.getByTestId(
            `${NotificationsDefaultSettings.DATA_TEST_NAMESPACE}${namespace}`,
        );
        await namespaceActions
            .getByTestId(`${NotificationsDefaultSettings.DATA_TEST_NAMESPACE_ACTIONS}${namespace}`)
            .getByRole("button", { name: "Actions" })
            .click();
        await this.page.getByTestId(`${NotificationsDefaultSettings.DATA_TEST_APPLY_DEFAULT}${namespace}`).click();

        const modal = this.page.getByTestId(NotificationsDefaultSettings.DATA_TEST_APPLY_DEFAULT_MODAL);
        await expect(modal).toBeVisible();
        await expect(modal).toContainText(new RegExp(`apply default ${namespace} settings to all users`, "i"));

        const applyResponse = this.page.waitForResponse(
            (response) =>
                response.request().method() === "POST" &&
                new URL(response.url()).pathname === "/adminv2/notifications/settings/apply-default",
        );
        await modal.getByTestId(NotificationsDefaultSettings.DATA_TEST_APPLY_DEFAULT_CONFIRM).click();
        expect((await applyResponse).ok()).toBeTruthy();
        await expect(modal).not.toBeVisible();
        await expect(this.page.getByTestId("toast")).toContainText("Settings applied successfully.");
    }
}
