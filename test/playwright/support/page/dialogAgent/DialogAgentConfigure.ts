import type { Locator, Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { expect } from "@schoolboxTest/playwright/support/integrations";

export default class DialogAgentConfigure {
    static readonly DATA_TEST_CONTAINER = "dialog-agent-configure-container";

    static readonly DATA_TEST_SANDBOX_DISABLED = "dac-sandbox-disabled";
    static readonly DATA_TEST_SANDBOX_ALERT_DIRTY = "dac-sandbox-alert-dirty";

    static readonly DATA_TEST_FORM_FIELD_TEXTAREA = "dac-form-field-"; // append field context id
    static readonly DATA_TEST_FORM_OPENING_MESSAGE_TEXTAREA = "dac-form-opening-message";
    static readonly DATA_TEST_FORM_RULES_TEXTAREA = "dac-form-rules";
    static readonly DATA_TEST_FORM_SAVE_BUTTON = "dac-form-save";

    constructor(
        private page: Page,
        private tester: AcceptanceTester,
    ) {}

    /**
     * @returns A Locator for the main container of the dialog agent configure page
     */
    public locateContainer(): Locator {
        return this.page.getByTestId(DialogAgentConfigure.DATA_TEST_CONTAINER);
    }

    /**
     * Verify that we are on the dialog agent configure page
     * @param activityTitle - optionally specify the title to verify the page for a specific activity
     */
    public async assertIsOnConfigurePage(activityTitle?: string): Promise<void> {
        const container = this.locateContainer();
        await expect(container).toBeVisible();

        if (activityTitle) {
            await expect(container.locator("h1").filter({ hasText: activityTitle })).toBeVisible();
        }
    }

    /**
     * Verify that the sandbox is currently disabled due to the initial configuration not being saved
     */
    public async assertSandboxDisabled(): Promise<void> {
        const sandboxDisabled = this.locateContainer().getByTestId(DialogAgentConfigure.DATA_TEST_SANDBOX_DISABLED);
        await expect(sandboxDisabled).toBeVisible();
    }

    /**
     * Locate all field context textareas on the page
     * @returns an array of Locators for the field context textareas
     */
    public async locateFormFieldTextareas(): Promise<Locator[]> {
        const anyFieldContextRegex = new RegExp(`^${DialogAgentConfigure.DATA_TEST_FORM_FIELD_TEXTAREA}`);
        return this.locateContainer().getByTestId(anyFieldContextRegex).all();
    }

    /**
     * Locate the opening message textarea
     * @returns Locator for the opening message textarea
     */
    public locateFormOpeningMessageTextarea(): Locator {
        return this.locateContainer().getByTestId(DialogAgentConfigure.DATA_TEST_FORM_OPENING_MESSAGE_TEXTAREA);
    }

    /**
     * Locate the rules textarea
     * @returns Locator for the rules textarea
     */
    public locateFormRulesTextarea(): Locator {
        return this.locateContainer().getByTestId(DialogAgentConfigure.DATA_TEST_FORM_RULES_TEXTAREA);
    }

    /**
     * Click the form submit button to save the configuration
     */
    public async clickSaveConfiguration(): Promise<void> {
        const saveButton = this.locateContainer().getByTestId(DialogAgentConfigure.DATA_TEST_FORM_SAVE_BUTTON);
        await saveButton.click();
    }

    /**
     * Verify that the sandbox shows the unsaved changes alert
     */
    public async assertSandboxUnsavedAlertVisible(): Promise<void> {
        const alert = this.locateContainer().getByTestId(DialogAgentConfigure.DATA_TEST_SANDBOX_ALERT_DIRTY);
        await expect(alert).toBeVisible();
    }

    /**
     * Verify that the sandbox does NOT show the unsaved changes alert
     */
    public async assertSandboxUnsavedAlertNotVisible(): Promise<void> {
        const alert = this.locateContainer().getByTestId(DialogAgentConfigure.DATA_TEST_SANDBOX_ALERT_DIRTY);
        await expect(alert).not.toBeVisible();
    }
}
