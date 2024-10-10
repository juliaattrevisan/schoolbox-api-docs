import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page } from "@playwright/test";

export default class CreateEvent {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL_CREATE_EVENT = "/calendar/event/create";

    static readonly TITLE = "input#title";

    static readonly SELECTOR_LEVEL = "#level";

    static readonly LEVEL_TYPE_PERSONAL = "Personal";

    static readonly LEVEL_TYPE_GROUP = "Group";

    static readonly LEVEL_TYPE_SCHOOL = "School";

    static readonly SUBMIT_BUTTON = "input#calendar-modify-submit";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to the create event page
     */
    async goto(): Promise<void> {
        await this.page.goto(CreateEvent.URL_CREATE_EVENT);
    }

    /**
     * wait Create Event button to be presented
     */

    async waitAddEventButtonToBePresented(): Promise<void> {
        await this.page.waitForSelector(CreateEvent.SUBMIT_BUTTON);
    }

    /**
     * typing an event title
     * @param eventTitle - The event title
     */
    async typeTitle(eventTitle: string): Promise<void> {
        await this.page.waitForTimeout(1000);
        await this.page.locator(CreateEvent.TITLE).fill(eventTitle);
    }

    /**
     * sets event level by passing its level: Personal, School, Group
     * @param levelType - The level type
     */
    async selectLevel(levelType: string): Promise<void> {
        await this.page.locator(CreateEvent.SELECTOR_LEVEL).selectOption({ label: levelType });
    }

    /**
     * click Add Event or Modify Event button
     */
    async clickSubmitEvent(): Promise<void> {
        await this.page.click(CreateEvent.SUBMIT_BUTTON);
    }

    /**
     * tick the checkbox for a group
     * @param folderId - The folder id
     */
    async selectGroup(folderId: string): Promise<void> {
        await this.page.click(`label[for='folder${folderId}']`);
    }

    /**
     * tick the checkbox for a role by passing its id
     * @param roleId - The role id
     */
    async selectRole(roleId: number): Promise<void> {
        await this.page.click(`label[for='role${roleId}']`);
    }

    /**
     * tick the checkbox for campus by passing its id
     * @param campusId - The campus id
     */
    async selectCampus(campusId: number): Promise<void> {
        await this.page.click(`label[for='campus${campusId}']`);
    }
}
