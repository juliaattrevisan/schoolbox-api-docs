import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class DeleteNode {
    private page: Page;

    private tester: AcceptanceTester;

    static readonly DATATEST_DELETE_BUTTON = "delete-nodes";

    static readonly DATATEST_CONFIRM_BUTTON = "confirm-delete-node";

    constructor(page: Page, tester: AcceptanceTester) {
        this.page = page;
        this.tester = tester;
    }

    /**
     * Clicks the delete button for selected nodes
     */
    async clickDeleteButton(): Promise<void> {
        await this.page.getByTestId(DeleteNode.DATATEST_DELETE_BUTTON).click();
    }

    /**
     * Waits for the confirmation modal to appear
     */
    async waitForConfirmModal(): Promise<void> {
        await expect(this.page.getByTestId(DeleteNode.DATATEST_CONFIRM_BUTTON)).toBeVisible();
    }

    /**
     * Clicks the confirm button to delete nodes
     */
    async clickConfirmButton(): Promise<void> {
        await this.page.getByTestId(DeleteNode.DATATEST_CONFIRM_BUTTON).click();
    }
}
