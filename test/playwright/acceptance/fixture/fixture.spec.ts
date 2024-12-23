/**
 * This test creates and modify fixtures and ensure the notification is sent according to the match date/time
 */
import { test } from "@schoolboxTest/playwright/support/integrations";

test.describe("Fixture tests", () => {
    test.beforeEach(async ({ tester }) => {
        tester.isGoingTo("clear all notifications");
        await tester.runShellCommand(
            "cd /usr/share/schoolbox/tools/notif; sudo php /usr/share/schoolbox/test/socket/destroy-rabbit-data.php",
        );
        await tester.loginAsAdmin();
    });

    // This test is not implemented yet, using to demonstrate tester.runShellCommand

    test("Match Notifications", ({ tester }) => {
        tester.isGoingTo("create a match in the past and expect no notification");
    });
});
