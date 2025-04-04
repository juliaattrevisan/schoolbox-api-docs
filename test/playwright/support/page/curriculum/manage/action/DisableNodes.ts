import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class DisableNodes {
    private page: Page;

    private tester: AcceptanceTester;

    static readonly DATA_TEST_DISABLE_BUTTON = "disable-nodes";

    static readonly DATA_TEST_ENABLE_BUTTON = "enable-nodes";

    static readonly DATA_TEST_CONFIRM_BUTTON = "confirm-enable-nodes";

    constructor(page: Page, tester: AcceptanceTester) {
        this.page = page;
        this.tester = tester;
    }

    /**
     * Clicks the disable button for selected nodes
     */
    async clickDisableButton(): Promise<void> {
        await this.page.getByTestId(DisableNodes.DATA_TEST_DISABLE_BUTTON).click();
    }

    /**
     * Clicks the enable button for selected nodes
     */
    async clickEnableButton(): Promise<void> {
        await this.page.getByTestId(DisableNodes.DATA_TEST_ENABLE_BUTTON).click();
    }

    /**
     * Waits for the confirmation modal to appear
     */
    async waitForConfirmModal(): Promise<void> {
        await expect(this.page.getByTestId(DisableNodes.DATA_TEST_CONFIRM_BUTTON)).toBeVisible();
    }

    /**
     * Clicks the confirm button to disable nodes
     */
    async clickConfirmButton(): Promise<void> {
        await this.page.getByTestId(DisableNodes.DATA_TEST_CONFIRM_BUTTON).click();
    }
}
