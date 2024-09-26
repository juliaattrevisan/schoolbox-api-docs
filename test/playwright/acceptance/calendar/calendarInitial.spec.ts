//* This test creates a personal event, checks visibility of the event as different users, deletes the event
//* creates personal, group and school level events and check if the dialog presents view, modify and delete buttons
//* 1. Provision sales.tar.gz data
//* 2. npx playwright test calendar/calendarInitial.spec.ts

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import Calendar from "@schoolboxTest/playwright/support/page/calendar/Calendar";
import CreateEvent from "@schoolboxTest/playwright/support/page/calendar/CreateEvent";

test.describe("Calendar tests", () => {
    test.beforeEach(async ({ tester, page }) => {
        await tester.logout();
        await tester.loginAsAdmin();
        const calendar = new Calendar(tester, page);
        tester.isGoingTo("calendar page");
        await calendar.goto();
    });

    /**
     * creates a personal event and check it is presented properly
     */
    test("Create personal event", async ({ tester, page }) => {
        const calendar = new Calendar(tester, page);
        const eventTitle = "My Test Event";

        tester.isGoingTo("create a personal event via calendar panel");
        await calendar.clickDayCalendar();
        await calendar.typeTitle(eventTitle);
        await calendar.clickCreateEvent();
        await page.waitForTimeout(1000);
        tester.isGoingTo("check the event is presented in the calendar");
        await expect(page.locator("table.fc-scrollgrid-sync-table")).toContainText(eventTitle);
    });

    /**
     * checks if alaress admin can view their personal event and if Eden Reed cannot view it
     */
    test("View the event as different users", async ({ tester, page }) => {
        const calendar = new Calendar(tester, page);

        const eventTitle = "My Test Event";
        const student = "Eden Reed";
        tester.isGoingTo("as alaress check if I can view the personal event I have created");
        await calendar.clickPreviousButton();
        await expect(page.locator("table.fc-scrollgrid-sync-table")).toContainText(eventTitle);
        tester.isGoingTo("as Eden Reed check if I cannot view alaress admin personal event");
        await tester.impersonate(student);
        await calendar.goto();
        await expect(page.locator("table.fc-scrollgrid-sync-table")).not.toContainText(eventTitle);
        await calendar.clickPreviousButton();
        await expect(page.locator("table.fc-scrollgrid-sync-table")).not.toContainText(eventTitle);
    });

    /**
     * deletes the event
     */
    test("Delete personal event", async ({ tester, page }) => {
        const calendar = new Calendar(tester, page);

        const eventTitle = "My Test Event";
        tester.isGoingTo("delete the personal event");
        await calendar.clickPreviousButton();
        await calendar.clickCalendarEvent(eventTitle);
        await calendar.clickDeleteDialogButton();
        await calendar.clickConfirmDelete();
        await expect(page.locator("table.fc-scrollgrid-sync-table")).not.toContainText(eventTitle);
    });

    /**
     * creates a personal, group and school event and check it presents the proper buttons on the dialog:
     * view, delete and modify buttons
     */
    test("View event dialog", async ({ tester, page, db }) => {
        const calendar = new Calendar(tester, page);
        const createEvent = new CreateEvent(tester, page);

        const user = "Jack Coles";
        const eventTitleGroup = "My Group Test Event";
        const eventTitlePersonal = "My Personal Test Event";
        const eventTitleSchool = "My School Test Event";

        tester.isGoingTo("as Jack Coles I create a personal event via calendar panel");
        await tester.impersonate(user);
        await calendar.goto();
        await calendar.clickDayCalendar();
        await calendar.typeTitle(eventTitlePersonal);
        await calendar.clickCreateEvent();
        await page.waitForTimeout(1000);
        tester.isGoingTo("check the event is presented in the calendar");
        await expect(page.locator("table.fc-scrollgrid-sync-table")).toContainText(eventTitlePersonal);
        tester.isGoingTo("check the view, modify and delete buttons are presented in the dialog");
        await calendar.clickCalendarEvent(eventTitlePersonal);
        await expect(page.getByTestId("view-event-modal-view-details-button")).toContainText("View details");
        await expect(page.getByTestId("view-event-modal-modify-button")).toContainText("Modify");
        await expect(page.getByTestId("view-event-modal-delete-button")).toContainText("Delete");

        tester.isGoingTo("delete the event so it does not impact in the next test");
        await db.updateInDatabase("event", { deleted_at: "2022-01-15 11:22:34" }, { "title like": eventTitlePersonal });

        tester.isGoingTo("as Jack Coles I create a group event");
        await createEvent.goto();
        await createEvent.typeTitle(eventTitleGroup);
        await createEvent.selectLevel("Group");
        await createEvent.selectGroup("55");
        await createEvent.clickSubmitEvent();
        tester.isGoingTo("check the event is presented in the calendar");
        await calendar.goto();
        await expect(page.locator("table.fc-scrollgrid-sync-table")).toContainText(eventTitleGroup);
        tester.isGoingTo("check the view, modify and delete buttons are presented in the dialog");
        await calendar.clickCalendarEvent(eventTitleGroup);
        await expect(page.getByTestId("view-event-modal-view-details-button")).toContainText("View details");
        await expect(page.getByTestId("view-event-modal-modify-button")).toContainText("Modify");
        await expect(page.getByTestId("view-event-modal-delete-button")).toContainText("Delete");

        tester.isGoingTo("delete the event so it does not impact in the next test");
        await db.updateInDatabase("event", { deleted_at: "2022-01-15 11:22:34" }, { "title like": eventTitleGroup });

        tester.isGoingTo("set Jack Coles as a Calendar moderator");

        await tester.logout();
        await tester.loginAsAdmin();
        await db.haveInDatabase("userAccess", {
            uid: 22,
            service: 2,
            sid: 2,
            type: 3,
            favourite: 0,
            created_at: "2020-01-15 11:22:34",
        });

        tester.isGoingTo("as Jack Coles I create a school event");
        await tester.impersonate(user);
        await createEvent.goto();
        await createEvent.typeTitle(eventTitleSchool);
        await createEvent.selectLevel("School");
        await createEvent.selectRole(2);
        await createEvent.selectCampus(2);
        await createEvent.clickSubmitEvent();
        tester.isGoingTo("check the event is presented in the calendar");
        await calendar.goto();
        await expect(page.locator("table.fc-scrollgrid-sync-table")).toContainText(eventTitleSchool);
        tester.isGoingTo("check the view, modify and delete buttons are presented in the dialog");
        await calendar.clickCalendarEvent(eventTitleSchool);
        await expect(page.getByTestId("view-event-modal-view-details-button")).toContainText("View details");
        await expect(page.getByTestId("view-event-modal-modify-button")).toContainText("Modify");
        await expect(page.getByTestId("view-event-modal-delete-button")).toContainText("Delete");

        tester.isGoingTo("delete the event so it does not impact in the next test");
        await db.updateInDatabase("event", { deleted_at: "2022-01-15 11:22:34" }, { "title like": eventTitleSchool });
    });

    /**
     * Soft deleting data to not impact the tests
     */
    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo(
            "update delete_at for the data created in this test so it does impact next suite of tests",
        );
        const eventTitlePersonal = "My Personal Test Event";
        const eventTitleGroup = "My Group Test Event";
        const eventTitleSchool = "My School Test Event";
        const eventTitle = "My Test Event";
        await db.updateInDatabase("event", { deleted_at: "2022-01-15 11:22:34" }, { "title like": eventTitlePersonal });
        await db.updateInDatabase("event", { deleted_at: "2022-01-15 11:22:34" }, { "title like": eventTitleGroup });
        await db.updateInDatabase("event", { deleted_at: "2022-01-15 11:22:34" }, { "title like": eventTitleSchool });
        await db.updateInDatabase("event", { deleted_at: "2022-01-15 11:22:34" }, { "title like": eventTitle });
        await db.dontHaveInDatabase("userAccess", { uid: 22, service: 2, sid: 2, type: 3 });
    });
});
