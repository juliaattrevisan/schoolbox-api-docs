/**
 * This test creates and modify fixtures and ensure the notification is sent according to the match date/time
 */
import { test } from "@schoolboxTest/playwright/support/integrations";
import Fixture from "@schoolboxTest/playwright/support/page/fixture/Fixture";

test.describe("Fixture match notification tests", () => {
    test.beforeEach(async ({ tester }) => {
        tester.isGoingTo("clear all notifications");
        // Run a special script if on CI since the machine is built differently
        const destroyFile = process.env.CI ? "destroy-rabbit-data-ci.php" : "destroy-rabbit-data.php";
        await tester.runShellCommand(
            `cd /usr/share/schoolbox/tools/notif; sudo php /usr/share/schoolbox/test/socket/${destroyFile}`,
        );

        await tester.loginAsAdmin();
    });

    test("Match in the past", async ({ tester, page, db }) => {
        const fixture = new Fixture(tester, page);

        tester.isGoingTo("create a match in the past and expect no notification");
        const pastStartDate = new Date();
        pastStartDate.setMinutes(pastStartDate.getMinutes() - 120);
        const pastFinishDate = new Date(pastStartDate);
        pastFinishDate.setMinutes(pastFinishDate.getMinutes() + 60);

        const matchName = "First Test Match";
        await fixture.createMatch(
            db,
            {
                [Fixture.SELECTOR_NAME]: matchName,
                [Fixture.SELECTOR_START_AT]: pastStartDate,
                [Fixture.SELECTOR_FINISH_AT]: pastFinishDate,
            },
            {
                [Fixture.SELECTOR_JS_VENUE]: Fixture.VENUE_OAKLEIGH_RECREATION,
                [Fixture.SELECTOR_JS_HOME_TEAM]: Fixture.TEAM_WARRIORS,
                [Fixture.SELECTOR_JS_AWAY_TEAM]: Fixture.TEAM_RAIDERS,
            },
        );

        tester.isGoingTo("expect not to recieve a notification as the match is in the past");
        await fixture.assertNotifNotExists(`You have an upcoming Basketball match ${matchName}`);
    });

    test("Match in 24 hours", async ({ tester, page, db }) => {
        const fixture = new Fixture(tester, page);

        tester.isGoingTo("create a match in 24 hours and expect notification");
        const matchName = "Second Test Match";
        const futureStartDate = new Date();
        futureStartDate.setMinutes(futureStartDate.getMinutes() + 30);
        const futureFinishDate = new Date(futureStartDate);
        futureFinishDate.setMinutes(futureFinishDate.getMinutes() + 90);

        const matchId = await fixture.createMatch(
            db,
            {
                [Fixture.SELECTOR_NAME]: matchName,
                [Fixture.SELECTOR_START_AT]: futureStartDate,
                [Fixture.SELECTOR_FINISH_AT]: futureFinishDate,
            },
            {
                [Fixture.SELECTOR_JS_VENUE]: Fixture.VENUE_OAKLEIGH_RECREATION,
                [Fixture.SELECTOR_JS_HOME_TEAM]: Fixture.TEAM_WARRIORS,
                [Fixture.SELECTOR_JS_AWAY_TEAM]: Fixture.TEAM_RAIDERS,
            },
        );

        await fixture.assertNotifExists(`You have an upcoming Basketball match ${matchName}`);

        tester.isGoingTo("match in 24 hours - no change and save - expect no notification");
        await fixture.updateMatch(matchId, {});
        await fixture.assertNotifNotExists(`Your upcoming Basketball match ${matchName} has been updated`);

        tester.isGoingTo("match in 24 hours - change title and save - expect notification");
        const newMatchName = "Updated Test Match";
        await fixture.updateMatch(matchId, { [Fixture.SELECTOR_NAME]: newMatchName });
        await fixture.assertNotifExists(`Your upcoming Basketball match ${newMatchName} has been updated`);

        tester.isGoingTo("match in 24 hours - change start time and save - expect notification");
        const updatedStartDateA = new Date();
        updatedStartDateA.setMinutes(updatedStartDateA.getMinutes() + 45);
        await fixture.updateMatch(matchId, { [Fixture.SELECTOR_START_AT]: updatedStartDateA });
        await fixture.assertNotifExists(`Your upcoming Basketball match ${newMatchName} has a new start time`);
    });

    test("Match in 24 hours - add itinerary", async ({ tester, page, db }) => {
        const fixture = new Fixture(tester, page);

        tester.isGoingTo("match in 24 hours - add itinerary and save - expect notification");
        const matchName = "Fourth Test Match";
        const startDate = new Date();
        startDate.setMinutes(startDate.getMinutes() + 30);
        const finishDate = new Date(startDate);
        finishDate.setMinutes(finishDate.getMinutes() + 120);

        const matchId4 = await fixture.createMatch(
            db,
            {
                [Fixture.SELECTOR_NAME]: matchName,
                [Fixture.SELECTOR_START_AT]: startDate,
                [Fixture.SELECTOR_FINISH_AT]: finishDate,
            },
            {
                [Fixture.SELECTOR_JS_VENUE]: Fixture.VENUE_OAKLEIGH_RECREATION,
                [Fixture.SELECTOR_JS_HOME_TEAM]: Fixture.TEAM_WARRIORS,
                [Fixture.SELECTOR_JS_AWAY_TEAM]: Fixture.TEAM_RAIDERS,
            },
        );

        await fixture.addItinerary(matchId4);
        await fixture.assertNotifExists(`Your upcoming Basketball match ${matchName} itinerary has been changed`);
    });

    test("Match in 24 hours - remove itinerary", async ({ tester, page, db }) => {
        const fixture = new Fixture(tester, page);

        tester.isGoingTo("match in 24 hours - remove itinerary and save - expect notification");
        const matchName = "Fifth Test Match";
        const startDate = new Date();
        startDate.setMinutes(startDate.getMinutes() + 30);
        const finishDate = new Date(startDate);
        finishDate.setMinutes(finishDate.getMinutes() + 120);

        const matchId5 = await fixture.createMatch(
            db,
            {
                [Fixture.SELECTOR_NAME]: matchName,
                [Fixture.SELECTOR_START_AT]: startDate,
                [Fixture.SELECTOR_FINISH_AT]: finishDate,
            },
            {
                [Fixture.SELECTOR_JS_VENUE]: Fixture.VENUE_OAKLEIGH_RECREATION,
                [Fixture.SELECTOR_JS_HOME_TEAM]: Fixture.TEAM_WARRIORS,
                [Fixture.SELECTOR_JS_AWAY_TEAM]: Fixture.TEAM_RAIDERS,
            },
        );

        await fixture.deleteItinerary(matchId5);
        await fixture.assertNotifExists(`Your upcoming Basketball match ${matchName} itinerary has been changed`);
    });

    test("Match in the future", async ({ tester, page, db }) => {
        const fixture = new Fixture(tester, page);

        tester.isGoingTo("create a match in the future and expect no immediate notification");
        const matchName = "Sixth Test Match";
        const futureStartDate = new Date();
        futureStartDate.setDate(futureStartDate.getDate() + 1);
        futureStartDate.setMinutes(futureStartDate.getMinutes() + 10);
        const futureFinishDateB = new Date(futureStartDate);
        futureFinishDateB.setDate(futureFinishDateB.getDate() + 1);
        futureFinishDateB.setMinutes(futureFinishDateB.getMinutes() + 120);

        const matchId6 = await fixture.createMatch(
            db,
            {
                [Fixture.SELECTOR_NAME]: matchName,
                [Fixture.SELECTOR_START_AT]: futureStartDate,
                [Fixture.SELECTOR_FINISH_AT]: futureFinishDateB,
            },
            {
                [Fixture.SELECTOR_JS_VENUE]: Fixture.VENUE_OAKLEIGH_RECREATION,
                [Fixture.SELECTOR_JS_HOME_TEAM]: Fixture.TEAM_WARRIORS,
                [Fixture.SELECTOR_JS_AWAY_TEAM]: Fixture.TEAM_RAIDERS,
            },
        );

        await fixture.assertNotifNotExists(`You have an upcoming Basketball match ${matchName}`);

        tester.isGoingTo("update the future match and expect no immediate notification");
        await fixture.updateMatch(matchId6, {});
        await fixture.assertNotifNotExists(`Your upcoming Basketball match ${matchName} has been updated`);

        tester.isGoingTo("change the title and expect no immediate notification");
        const modifiedMatchName = "Modified Sixth Test Match";
        const newFutureStartDate = new Date();
        newFutureStartDate.setDate(newFutureStartDate.getDate() + 1);
        newFutureStartDate.setMinutes(newFutureStartDate.getMinutes() + 1);

        await fixture.updateMatch(matchId6, {
            [Fixture.SELECTOR_NAME]: modifiedMatchName,
            [Fixture.SELECTOR_START_AT]: newFutureStartDate,
        });

        await fixture.assertNotifNotExists(`Your upcoming Basketball match ${modifiedMatchName} has been updated`);

        tester.isGoingTo("wait 60 seconds for the notification to trigger close to the match time");
        await page.waitForTimeout(60000);
        await fixture.assertNotifExists(`You have an upcoming Basketball match ${modifiedMatchName}`);
        await fixture.assertNotifNotExists(`Your upcoming Basketball match ${modifiedMatchName} has been updated`);
    });

    /**
     * Soft deleting data to not impact the tests
     */
    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo(
            "update delete_at for the data created in this test so it does impact next suite of tests",
        );
        await db.updateInDatabase(
            "sportsfixtures_match",
            { deleted_at: "2022-01-15 11:22:34" },
            { "name like": "First%" },
        );
        await db.updateInDatabase(
            "sportsfixtures_match",
            { deleted_at: "2022-01-15 11:22:34" },
            { "name like": "Second%" },
        );
        await db.updateInDatabase(
            "sportsfixtures_match",
            { deleted_at: "2022-01-15 11:22:34" },
            { "name like": "Third%" },
        );
        await db.updateInDatabase(
            "sportsfixtures_match",
            { deleted_at: "2022-01-15 11:22:34" },
            { "name like": "Fourth%" },
        );
        await db.updateInDatabase(
            "sportsfixtures_match",
            { deleted_at: "2022-01-15 11:22:34" },
            { "name like": "Fifth%" },
        );
        await db.updateInDatabase(
            "sportsfixtures_match",
            { deleted_at: "2022-01-15 11:22:34" },
            { "name like": "Sixth%" },
        );
        await db.updateInDatabase(
            "sportsfixtures_match_itineraryitem",
            { deleted_at: "2022-01-15 11:22:34" },
            { "description like": "aaa" },
        );
        await db.updateInDatabase(
            "sportsfixtures_match_itineraryitem",
            { deleted_at: "2022-01-15 11:22:34" },
            { "description like": "lalala" },
        );
    });
});
