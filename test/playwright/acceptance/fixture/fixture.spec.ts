/**
 * This test creates and modify fixtures and ensure the notification is sent according to the match date/time
 */
import { test } from "@schoolboxTest/playwright/support/integrations";
import Fixture from "@schoolboxTest/playwright/support/page/fixture/Fixture";

test.describe("Fixture tests", () => {
    test.beforeEach(async ({ tester }) => {
        tester.isGoingTo("clear all notifications");
        await tester.runShellCommand(
            "cd /usr/share/schoolbox/tools/notif; sudo php /usr/share/schoolbox/test/socket/destroy-rabbit-data.php",
        );
        await tester.loginAsAdmin();
    });

    test("Match Notifications", async ({ tester, page, db }) => {
        const fixture = new Fixture(tester, page);

        tester.isGoingTo("create a match in the past and expect no notification");
        const pastStartDate = new Date();
        pastStartDate.setMinutes(pastStartDate.getMinutes() - 120);
        const pastFinishDate = new Date(pastStartDate);
        pastFinishDate.setMinutes(pastFinishDate.getMinutes() + 60);

        const matchName1 = "First Test Match";
        await fixture.createMatch(
            db,
            {
                [Fixture.SELECTOR_NAME]: matchName1,
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
        await fixture.assertNotifNotExists(`You have an upcoming Basketball match ${matchName1}`);

        tester.isGoingTo("create a match in 24 hours and expect notification");
        const matchName2 = "Second Test Match";
        const futureStartDate = new Date();
        futureStartDate.setMinutes(futureStartDate.getMinutes() + 30);
        const futureFinishDate = new Date(futureStartDate);
        futureFinishDate.setMinutes(futureFinishDate.getMinutes() + 90);

        const matchIdA = await fixture.createMatch(
            db,
            {
                [Fixture.SELECTOR_NAME]: matchName2,
                [Fixture.SELECTOR_START_AT]: futureStartDate,
                [Fixture.SELECTOR_FINISH_AT]: futureFinishDate,
            },
            {
                [Fixture.SELECTOR_JS_VENUE]: Fixture.VENUE_OAKLEIGH_RECREATION,
                [Fixture.SELECTOR_JS_HOME_TEAM]: Fixture.TEAM_WARRIORS,
                [Fixture.SELECTOR_JS_AWAY_TEAM]: Fixture.TEAM_RAIDERS,
            },
        );

        await fixture.assertNotifExists(`You have an upcoming Basketball match ${matchName2}`);

        tester.isGoingTo("match in 24 hours - no change and save - expect no notification");
        await fixture.updateMatch(matchIdA, {});
        await fixture.assertNotifNotExists(`Your upcoming Basketball match ${matchName2} has been updated`);

        tester.isGoingTo("match in 24 hours - change title and save - expect notification");
        const matchName3 = "Updated Test Match";
        await fixture.updateMatch(matchIdA, { [Fixture.SELECTOR_NAME]: matchName3 });
        await fixture.assertNotifExists(`Your upcoming Basketball match ${matchName3} has been updated`);

        tester.isGoingTo("match in 24 hours - change start time and save - expect notification");
        const updatedStartDateA = new Date();
        updatedStartDateA.setMinutes(updatedStartDateA.getMinutes() + 45);
        await fixture.updateMatch(matchIdA, { [Fixture.SELECTOR_START_AT]: updatedStartDateA });
        await fixture.assertNotifExists(`Your upcoming Basketball match ${matchName3} has a new start time`);

        tester.isGoingTo("match in 24 hours - add itinerary and save - expect notification");
        const matchName4 = "Fourth Test Match";
        const startDate4 = new Date();
        startDate4.setMinutes(startDate4.getMinutes() + 30);
        const finishDate4 = new Date(startDate4);
        finishDate4.setMinutes(finishDate4.getMinutes() + 120);

        const matchId4 = await fixture.createMatch(
            db,
            {
                [Fixture.SELECTOR_NAME]: matchName4,
                [Fixture.SELECTOR_START_AT]: startDate4,
                [Fixture.SELECTOR_FINISH_AT]: finishDate4,
            },
            {
                [Fixture.SELECTOR_JS_VENUE]: Fixture.VENUE_OAKLEIGH_RECREATION,
                [Fixture.SELECTOR_JS_HOME_TEAM]: Fixture.TEAM_WARRIORS,
                [Fixture.SELECTOR_JS_AWAY_TEAM]: Fixture.TEAM_RAIDERS,
            },
        );

        await fixture.addItinerary(matchId4);
        await fixture.assertNotifExists(`Your upcoming Basketball match ${matchName4} itinerary has been changed`);

        tester.isGoingTo("match in 24 hours - remove itinerary and save - expect notification");
        const matchName5 = "Fifth Test Match";
        const startDate5 = new Date();
        startDate5.setMinutes(startDate5.getMinutes() + 30);
        const finishDate5 = new Date(startDate5);
        finishDate5.setMinutes(finishDate5.getMinutes() + 120);

        const matchId5 = await fixture.createMatch(
            db,
            {
                [Fixture.SELECTOR_NAME]: matchName5,
                [Fixture.SELECTOR_START_AT]: startDate5,
                [Fixture.SELECTOR_FINISH_AT]: finishDate5,
            },
            {
                [Fixture.SELECTOR_JS_VENUE]: Fixture.VENUE_OAKLEIGH_RECREATION,
                [Fixture.SELECTOR_JS_HOME_TEAM]: Fixture.TEAM_WARRIORS,
                [Fixture.SELECTOR_JS_AWAY_TEAM]: Fixture.TEAM_RAIDERS,
            },
        );

        await fixture.deleteItinerary(matchId5);
        await fixture.assertNotifExists(`Your upcoming Basketball match ${matchName5} itinerary has been changed`);

        tester.isGoingTo("create a match in the future and expect no immediate notification");
        const matchName6 = "Sixth Test Match";
        const futureStartDateB = new Date();
        futureStartDateB.setDate(futureStartDateB.getDate() + 1);
        futureStartDateB.setMinutes(futureStartDateB.getMinutes() + 10);
        const futureFinishDateB = new Date(futureStartDateB);
        futureFinishDateB.setDate(futureFinishDateB.getDate() + 1);
        futureFinishDateB.setMinutes(futureFinishDateB.getMinutes() + 120);

        const matchId6 = await fixture.createMatch(
            db,
            {
                [Fixture.SELECTOR_NAME]: matchName6,
                [Fixture.SELECTOR_START_AT]: futureStartDateB,
                [Fixture.SELECTOR_FINISH_AT]: futureFinishDateB,
            },
            {
                [Fixture.SELECTOR_JS_VENUE]: Fixture.VENUE_OAKLEIGH_RECREATION,
                [Fixture.SELECTOR_JS_HOME_TEAM]: Fixture.TEAM_WARRIORS,
                [Fixture.SELECTOR_JS_AWAY_TEAM]: Fixture.TEAM_RAIDERS,
            },
        );

        await fixture.assertNotifNotExists(`You have an upcoming Basketball match ${matchName6}`);

        tester.isGoingTo("update the future match and expect no immediate notification");
        await fixture.updateMatch(matchId6, {});
        await fixture.assertNotifNotExists(`Your upcoming Basketball match ${matchName6} has been updated`);

        tester.isGoingTo("change the title and expect no immediate notification");
        const modifiedMatchName = "Modified Sixth Test Match";
        const futureStartDateC = new Date();
        futureStartDateC.setDate(futureStartDateC.getDate() + 1);
        futureStartDateC.setMinutes(futureStartDateC.getMinutes() + 1);

        await fixture.updateMatch(matchId6, {
            [Fixture.SELECTOR_NAME]: modifiedMatchName,
            [Fixture.SELECTOR_START_AT]: futureStartDateC,
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
