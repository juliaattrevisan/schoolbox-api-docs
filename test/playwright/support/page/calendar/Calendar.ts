import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page } from "@playwright/test";

export default class Calendar {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/calendar/week";

    static readonly DAY_PANEL = "td.fc-day";

    static readonly CALENDAR_EVENT = "a.fc-daygrid-event";

    static readonly CALENDAR_PREVIOUS_BUTTON = "button.fc-customPrev-button.fc-button.fc-button-primary";

    static readonly EVENT_DIALOG = "div#createEventPanel";

    static readonly DIALOG_TITLE = "input#createEventBubbleTitle";

    static readonly DATA_TEST_DIALOG_CREATE_BUTTON = `create-event-modal-create-button`;

    static readonly BUTTON_LINK = "a.button";

    static readonly DATA_TEST_DIALOG_DELETE = "view-event-modal-delete-button";

    static readonly DATA_TEST_DELETE_CONFIRMATION_DIALOG = "event-delete-modal-confirm-delete-button";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to the calendar page
     */
    async goto(): Promise<void> {
        await this.page.goto(Calendar.URL);
    }

    /**
     * click a day field in the calendar
     */
    async clickDayCalendar(): Promise<void> {
        await this.page.click(Calendar.DAY_PANEL);
    }

    /**
     * click previous button in the calendar
     */
    async clickPreviousButton(): Promise<void> {
        await this.page.click(Calendar.CALENDAR_PREVIOUS_BUTTON);
    }

    /**
     * click an event in the calendar grid
     * @param evenTitle - The event title
     */
    async clickCalendarEvent(evenTitle: string): Promise<void> {
        await this.page.click(`text=${evenTitle}`);
    }

    /**
     * DIALOG
     */

    /**
     * typing a title in the calendar dialog
     * @param dialogEventTitle - The dialog event title
     */
    async typeTitle(dialogEventTitle: string): Promise<void> {
        await this.page.waitForTimeout(1000);
        await this.page.locator(Calendar.DIALOG_TITLE).click();
        await this.page.keyboard.type(dialogEventTitle);
    }

    /**
     * click Create button in the calendar dialog
     */
    async clickCreateEvent(): Promise<void> {
        await this.page.waitForTimeout(3000);
        await this.page.getByTestId(Calendar.DATA_TEST_DIALOG_CREATE_BUTTON).click();
    }

    /**
     * click the button Delete the calendar event dialog
     */
    async clickDeleteDialogButton(): Promise<void> {
        await this.page.getByTestId(Calendar.DATA_TEST_DIALOG_DELETE).click();
    }

    /**
     * click the button Delete in the confirmation dialog
     */
    async clickConfirmDelete(): Promise<void> {
        await this.page.getByTestId(Calendar.DATA_TEST_DELETE_CONFIRMATION_DIALOG).click();
    }
}
