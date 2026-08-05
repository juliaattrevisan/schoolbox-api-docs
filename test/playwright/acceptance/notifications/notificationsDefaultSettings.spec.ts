/**
 * Tests the notification default settings help content.
 *
 * Prerequisites:
 * 1. Provision sales.tar.gz data
 *
 * Execute: DOCKER=1 npx playwright test notificationsDefaultSettings.spec.ts
 */

import { test } from "@schoolboxTest/playwright/support/integrations";
import NotificationsDefaultSettings, {
    NOTIFICATION_DELIVERY_OPTIONS,
} from "@schoolboxTest/playwright/support/page/notifications/NotificationsDefaultSettings";

test.describe("Notification default settings", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("Displays the notification delivery options help", async ({ tester, page }) => {
        const notificationsDefaultSettings = new NotificationsDefaultSettings(tester, page);

        try {
            tester.isGoingTo("Open the global notification settings page");
            await notificationsDefaultSettings.goto();

            tester.isGoingTo("Open the notification delivery options help");
            await notificationsDefaultSettings.openDeliveryOptions();

            tester.isGoingTo("Confirm the notification delivery options are presented");
            await notificationsDefaultSettings.expectDeliveryOptionsModal();
            for (const option of NOTIFICATION_DELIVERY_OPTIONS) {
                await notificationsDefaultSettings.expectDeliveryOption(option);
            }
        } finally {
            if (await notificationsDefaultSettings.locateDeliveryOptionsModal().isVisible()) {
                await tester.common.closeModal();
            }
        }
    });
});
