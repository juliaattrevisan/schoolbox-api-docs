/**
 * Tests notification defaults, applying them to users, and instant notification delivery.
 *
 * Prerequisites:
 * 1. Provision sales.tar.gz data
 *
 * Execute: DOCKER=1 npx playwright test notificationsDefaultSettings.spec.ts
 */

import { expect, test } from "@schoolboxTest/playwright/support/integrations";
import NotificationsDefaultSettings, {
    NOTIFICATION_DELIVERY_OPTIONS,
} from "@schoolboxTest/playwright/support/page/notifications/NotificationsDefaultSettings";
import NotificationSettings from "@schoolboxTest/playwright/support/page/notifications/NotificationSettings";
import Form from "@schoolboxTest/playwright/support/page/news/Form";
import Common from "@schoolboxTest/playwright/support/page/Common";

const NEWS_NAMESPACE = "news";
const NEWS_POSTED_SOURCE = "posted";
const NEWS_ARTICLE_TITLE = `Automation Test notification default settings ${Date.now()}`;
const NEWS_POSTED_STUDENT_SOURCE_ID = 34;
const NEWS_POSTED_PARENT_SOURCE_ID = 35;
const NEWS_POSTED_STAFF_SOURCE_ID = 36;
const TEACHER_ROLE_ID = 2;
const SENIOR_AND_JUNIOR_STUDENT_ROLE_IDS = [3, 5];
const PARENT_ROLE_ID = 4;
const MIDDLE_STUDENT_ROLE_ID = 6;

test.describe.serial("Notification default settings", () => {
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

    test("Configures News defaults and applies them to all users", async ({ tester, page }) => {
        const notificationsDefaultSettings = new NotificationsDefaultSettings(tester, page);

        tester.isGoingTo("set Middle Student News notifications to Instant");
        await notificationsDefaultSettings.goto("student");
        await notificationsDefaultSettings.setRoleDelivery(
            NEWS_NAMESPACE,
            NEWS_POSTED_SOURCE,
            "Middle Student",
            "Instant",
        );

        tester.isGoingTo("set all Staff News notifications to Muted");
        await notificationsDefaultSettings.goto("staff");
        await notificationsDefaultSettings.setRoleDelivery(NEWS_NAMESPACE, NEWS_POSTED_SOURCE, "Teacher", "Muted");

        tester.isGoingTo("set Parent News notifications to Instant");
        await notificationsDefaultSettings.goto("parent");
        await notificationsDefaultSettings.setRoleDelivery(NEWS_NAMESPACE, NEWS_POSTED_SOURCE, "Parent", "Instant");

        tester.isGoingTo("apply the News defaults to all existing users from the News three-dot menu");
        await notificationsDefaultSettings.goto();
        await notificationsDefaultSettings.applyDefaultsToAllUsers(NEWS_NAMESPACE);
    });

    test("Creates News for the Secondary campus including parents", async ({ tester, page, db }) => {
        const newsForm = new Form(tester, page);
        const publishDate = new Date();
        const expiryDate = new Date();
        publishDate.setMinutes(publishDate.getMinutes() - 2);
        expiryDate.setDate(expiryDate.getDate() + 7);

        tester.isGoingTo("remove an earlier copy of the SA-20501 News article if a previous run left one behind");
        await db.updateInDatabase(
            "comms_news_article",
            { deleted_at: "2022-01-15 11:22:34" },
            { title: NEWS_ARTICLE_TITLE, deleted_at: null },
        );

        tester.isGoingTo("create a News article for the Secondary campus and parents of its students");
        await newsForm.goto();
        await newsForm.fillHeadline(NEWS_ARTICLE_TITLE);
        await newsForm.fillArticle("News notification acceptance coverage for SA-20501.");
        await newsForm.fillPublishDate(publishDate);
        await newsForm.fillExpiryDate(expiryDate);
        await newsForm.selectAudienceOption("Campuses");
        await newsForm.checkboxAudience("Secondary");
        await newsForm.clickAddAudience();
        await newsForm.submit();
    });

    test("Applies News defaults and delivers notifications to the configured users", async ({ tester, page }) => {
        const notificationSettings = new NotificationSettings(page);

        tester.isGoingTo("check Jana Reed has the Instant default and News notification");
        await tester.impersonateViaUserProfilePage(Common.USER_JANAREED);
        await notificationSettings.goto();
        await notificationSettings.expectDelivery(NEWS_NAMESPACE, NEWS_POSTED_SOURCE, "Instant");
        await tester.common.findNotification(NEWS_ARTICLE_TITLE, true);

        tester.isGoingTo("check Jenni Reed has the Instant default and News notification");
        await tester.impersonateViaUserProfilePage(Common.USER_JENNIREED);
        await notificationSettings.goto();
        await notificationSettings.expectDelivery(NEWS_NAMESPACE, NEWS_POSTED_SOURCE, "Instant");
        await tester.common.findNotification(NEWS_ARTICLE_TITLE, true);

        tester.isGoingTo("check Jack Coles has the Muted default and no News notification");
        await tester.impersonateViaUserProfilePage(Common.USER_JACKCOLES);
        await notificationSettings.goto();
        await notificationSettings.expectDelivery(NEWS_NAMESPACE, NEWS_POSTED_SOURCE, "Muted");
        const notification = await tester.common.findNotification(NEWS_ARTICLE_TITLE, false, 1);
        await expect(notification).toHaveCount(0);
    });

    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("restore the provisioned News notification defaults and remove the test article");

        await db.updateInDatabase(
            "msg_route_perm",
            { role_default: "DI" },
            {
                msg_source_id: NEWS_POSTED_STUDENT_SOURCE_ID,
                role_id: SENIOR_AND_JUNIOR_STUDENT_ROLE_IDS,
            },
        );
        await db.updateInDatabase(
            "msg_route_perm",
            { mask: "MU", role_default: "MU" },
            { msg_source_id: NEWS_POSTED_STUDENT_SOURCE_ID, role_id: MIDDLE_STUDENT_ROLE_ID },
        );
        await db.updateInDatabase(
            "msg_route_perm",
            { role_default: "DI" },
            { msg_source_id: NEWS_POSTED_STAFF_SOURCE_ID, role_id: TEACHER_ROLE_ID },
        );
        await db.updateInDatabase(
            "msg_route_perm",
            { role_default: "DI" },
            { msg_source_id: NEWS_POSTED_PARENT_SOURCE_ID, role_id: PARENT_ROLE_ID },
        );

        await db.updateInDatabase(
            "comms_news_article",
            { deleted_at: "2022-01-15 11:22:34" },
            { title: NEWS_ARTICLE_TITLE, deleted_at: null },
        );
    });
});
