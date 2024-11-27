/**
 *
 * creates an event in the calendar, modifies it and ensure the proper date is presented in the date picker
 *
 * Steps:
 * 1. Provision sales.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /usr/share/schoolbox && ./vendor/bin/codecept run --steps Acceptance DatepickerCest
 *
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import Calendar from "@schoolboxTest/playwright/support/page/calendar/Calendar";
import Datepicker from "@schoolboxTest/playwright/support/page/calendar/DatePicker";
import Common from "@schoolboxTest/playwright/support/page/Common";
import CreateEvent from "@schoolboxTest/playwright/support/page/calendar/CreateEvent";

test.describe("Datepicker tests", () => {
    test.beforeEach(async ({ tester, page }) => {
        await tester.loginAsAdmin();
        const calendar = new Calendar(tester, page);
        await calendar.goto();
    });

    /**
     * Event modification form scenario
     */
    test("Add event", async ({ tester, page }) => {
        const calendar = new Calendar(tester, page);
        const datepicker = new Datepicker(tester, page);
        const common = new Common(page);
        const createEvent = new CreateEvent(tester, page);

        tester.isGoingTo("navigate to the calendar page and create an event");
        await calendar.goto();
        await calendar.clickDayCalendar();
        await common.waitForModalToBeOpen();
        await calendar.typeTitle("Date Picker Test");
        await calendar.clickCreateEvent();
        await common.waitForModalToBeClosed();
        tester.isGoingTo("check the event is presented in the calendar");
        await expect(page.locator("table.fc-scrollgrid-sync-table")).toContainText("Date Picker Test");
        tester.isGoingTo("modify the event and check the proper date is presented in the date picker");
        await calendar.goto();
        await calendar.clickCalendarEvent("Date Picker Test");
        await common.waitForModalToBeOpen();
        await datepicker.clickEventDialogModifyButton();
        await expect(page.locator("legend").first()).toContainText("Overview");
        await createEvent.fillDate("19/06/2017");
        await page.waitForTimeout(1000);
        await datepicker.clickSelectDateButton();
        await expect(page.getByLabel("June 19, 2017")).toHaveAttribute("class", "flatpickr-day selected");
    });

    /**
     * Soft deleting data to not impact the tests
     */
    test.afterAll(async ({ db }) => {
        await db.updateInDatabase("event", { deleted_at: "2022-01-15 11:22:34" }, { "title like": "Date Picker Test" });
    });
});
