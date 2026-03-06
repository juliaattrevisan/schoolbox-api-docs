import type { Locator, Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { expect } from "@schoolboxTest/playwright/support/integrations";

export default class DialogAgentViewResponse {
    static readonly DATA_TEST_CONTAINER = "dialog-agent-view-response-container";

    constructor(
        private page: Page,
        private tester: AcceptanceTester,
    ) {}

    /**
     * @returns A Locator for the main container of the dialog agent view response page
     */
    public locateContainer(): Locator {
        return this.page.getByTestId(DialogAgentViewResponse.DATA_TEST_CONTAINER);
    }

    /**
     * Verify that we are on the dialog agent view response page
     * @param activityTitle - optionally specify the title to verify the page for a specific activity
     */
    public async assertIsOnViewResponsePage(activityTitle?: string): Promise<void> {
        const container = this.locateContainer();
        await expect(container).toBeVisible();

        if (activityTitle) {
            await expect(this.page.locator("h1").filter({ hasText: activityTitle })).toBeVisible();
        }
    }
}
