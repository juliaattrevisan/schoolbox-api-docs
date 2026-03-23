import type { Locator, Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { expect } from "@schoolboxTest/playwright/support/integrations";

export default class DialogAgentResponse {
    static readonly DATA_TEST_CONTAINER = "dialog-agent-response-container";
    static readonly DATA_TEST_SUBMIT = "dialog-agent-response-submit";
    static readonly DATA_TEST_SUBMIT_CONFIRM = "dialog-agent-response-submit-confirm";
    static readonly DATA_TEST_REQUEST_HELP = "dialog-agent-response-request-help";
    static readonly DATA_TEST_REQUEST_HELP_DISMISS = "dialog-agent-response-request-help-dismiss";

    constructor(
        private page: Page,
        private tester: AcceptanceTester,
    ) {}

    /**
     * @returns A Locator for the main container of the dialog agent response page
     */
    public locateContainer(): Locator {
        return this.page.getByTestId(DialogAgentResponse.DATA_TEST_CONTAINER);
    }

    /**
     * Verify that we are on the dialog agent response page
     * @param activityTitle - optionally specify the title to verify the page for a specific activity
     */
    public async assertIsOnResponsePage(activityTitle?: string): Promise<void> {
        const container = this.locateContainer();
        await expect(container).toBeVisible();

        if (activityTitle) {
            await expect(this.page.locator("h1").filter({ hasText: activityTitle })).toBeVisible();
        }
    }

    /**
     * Click the submit button
     */
    public async clickSubmit(): Promise<void> {
        const submitButton = this.page.getByTestId(DialogAgentResponse.DATA_TEST_SUBMIT);
        await submitButton.click();
    }

    /**
     * Click the confirm submission button in the confirmation modal
     */
    public async clickConfirmSubmission(): Promise<void> {
        const confirmSubmissionButton = this.page.getByTestId(DialogAgentResponse.DATA_TEST_SUBMIT_CONFIRM);
        await confirmSubmissionButton.click();
    }

    /**
     * @returns Locator for the request help button
     */
    public locateRequestHelpButton(): Locator {
        return this.page.getByTestId(DialogAgentResponse.DATA_TEST_REQUEST_HELP);
    }

    /**
     * Click the request help button to request teacher help
     */
    public async clickRequestHelp(): Promise<void> {
        await this.locateRequestHelpButton().click();
    }

    /**
     * @returns Locator for the dismiss request help button
     */
    public locateDismissRequestHelpButton(): Locator {
        return this.page.getByTestId(DialogAgentResponse.DATA_TEST_REQUEST_HELP_DISMISS);
    }

    /**
     * Click the dismiss request help button to dismiss a previously made teacher help request
     */
    public async clickDismissRequestHelp(): Promise<void> {
        await this.locateDismissRequestHelpButton().click();
    }
}
