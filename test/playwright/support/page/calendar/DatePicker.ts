import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page } from "@playwright/test";

export default class Datepicker {
    private tester: AcceptanceTester;

    private page: Page;

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Check if the calendar picker date is the same as the expected date
     */
    async clickSelectDateButton(): Promise<void> {
        await this.page.locator('a[class="adtp-btn icon-calendar"]').first().click();
        await this.tester.common.waitForModalToBeOpen();
    }

    /**
     * click event dialog modify button
     */
    async clickEventDialogModifyButton(): Promise<void> {
        await this.page.getByTestId("view-event-modal-modify-button").click();
    }
}
