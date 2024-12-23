import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class DisableNodes {
    private page: Page;

    private tester: AcceptanceTester;

    static readonly DATATEST_DISABLE_BUTTON = "disable-nodes";

    static readonly DATATEST_CONFIRM_BUTTON = "confirm-enable-nodes";

    constructor(page: Page, tester: AcceptanceTester) {
        this.page = page;
        this.tester = tester;
    }

    /**
     * Clicks the disable button for selected nodes
     */
    async clickDisableButton(): Promise<void> {
        await this.page.getByTestId(DisableNodes.DATATEST_DISABLE_BUTTON).click();
    }

    /**
     * Waits for the confirmation modal to appear
     */
    async waitForConfirmModal(): Promise<void> {
        await expect(this.page.getByTestId(DisableNodes.DATATEST_CONFIRM_BUTTON)).toBeVisible();
    }

    /**
     * Clicks the confirm button to disable nodes
     */
    async clickConfirmButton(): Promise<void> {
        await this.page.getByTestId(DisableNodes.DATATEST_CONFIRM_BUTTON).click();
    }
}
