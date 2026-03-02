import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class DialogAgentConfigure {
    static readonly DATA_TEST_CONTAINER = "dialog-agent-configure-container";

    constructor(
        private page: Page,
        private tester: AcceptanceTester,
    ) {}

    /**
     * Verify that we are on the dialog agent configure page
     * @param activityTitle - optionally specify the title to verify the page for a specific activity
     */
    public async assertIsOnConfigurePage(activityTitle?: string): Promise<void> {
        const container = this.page.getByTestId(DialogAgentConfigure.DATA_TEST_CONTAINER);
        await expect(container).toBeVisible();

        if (activityTitle) {
            await expect(container.locator("h1").filter({ hasText: activityTitle })).toBeVisible();
        }
    }
}
