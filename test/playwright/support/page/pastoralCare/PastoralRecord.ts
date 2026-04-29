import type { Page, Locator } from "@playwright/test";
import { expect } from "@playwright/test";

export default class PastoralRecord {
    // Playwright page instance used to interact with the pastoral record UI.
    private page: Page;

    // Route template for a specific pastoral record page.
    static readonly URL = "/pastoral/student/{studentId}/record/{recordId}";

    // Selector for the general information panel on the pastoral record page.
    static readonly SELECTOR_PANEL_GENERAL = "div#panel-general";

    // Selector for the confidential panel on the pastoral record page.
    static readonly SELECTOR_PANEL_CONFIDENTIAL = "div#panel-confidential";

    // Selector for the tab or link that opens the confidential panel on the pastoral record page.
    static readonly SELECTOR_PANEL_CONFIDENTIAL_LINK = "a#panel-confidential";

    // Test id for the list that contains pastoral follow-up actions on the pastoral record page.
    static readonly DATA_TEST_ACTIONS_LIST = "pastoral-record-actions-list";

    // Test id template for a pastoral action checkbox input on the pastoral record page.
    static readonly DATA_TEST_PASTORAL_ACTION_INPUT = "pastoral-record-input-action-{actionId}";

    // Test id template for a pastoral action label element on the pastoral record page.
    static readonly DATA_TEST_PASTORAL_ACTION_LABEL = "pastoral-record-action-label-{actionId}";

    // Test id template for the status element of a pastoral action on the pastoral record page.
    static readonly DATA_TEST_PASTORAL_ACTION_STATUS = "pastoral-record-action-status-{actionId}";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Navigates to a specific pastoral record page
     * @param userId - The id of the user to view the pastoral record for
     * @param recordId - The id of the pastoral record to view
     */
    async goto(userId: number, recordId: number): Promise<void> {
        await this.page.goto(
            PastoralRecord.URL.replace("{studentId}", userId.toString()).replace("{recordId}", recordId.toString()),
        );
    }

    /**
     * check the general panel contains the text
     * @param text - The text to check
     */
    async seeInGeneralPanel(text: string): Promise<void> {
        await expect(this.page.locator(PastoralRecord.SELECTOR_PANEL_GENERAL)).toContainText(text);
    }

    /**
     * click confidential panel link
     */
    async clickConfidentialPanelLink(): Promise<void> {
        await this.page.locator(PastoralRecord.SELECTOR_PANEL_CONFIDENTIAL_LINK).click();
    }

    /**
     * check the confidential panel contains the text
     * @param text - The text to check
     */
    async seeInConfidentialPanel(text: string): Promise<void> {
        await expect(this.page.locator(PastoralRecord.SELECTOR_PANEL_CONFIDENTIAL)).toContainText(text);
    }

    /**
     * Get the pastoral actions list
     * @returns The pastoral actions list locator
     */
    getActionsList(): Locator {
        return this.page.getByTestId(PastoralRecord.DATA_TEST_ACTIONS_LIST);
    }

    /**
     * Get a pastoral action checkbox by action id
     * @param actionId - The action id
     * @returns The action checkbox locator
     */
    getActionCheckbox(actionId: number): Locator {
        // return this.page.locator(`#action-${actionId}`);
        return this.page.getByTestId(
            PastoralRecord.DATA_TEST_PASTORAL_ACTION_INPUT.replace("{actionId}", actionId.toString()),
        );
    }

    /**
     * Get a pastoral action label by action id
     * @param actionId - The action id
     * @returns The action label locator
     */
    getActionLabel(actionId: number): Locator {
        // return this.page.locator(`label[for="action-${actionId}"]`);
        return this.page.getByTestId(
            PastoralRecord.DATA_TEST_PASTORAL_ACTION_LABEL.replace("{actionId}", actionId.toString()),
        );
    }

    /**
     * Get the status container for a completed action
     * @param actionId - The action id
     * @returns The completed action status locator
     */
    getActionStatus(actionId: number): Locator {
        // return this.page.locator(`[data-action-status-${actionId}]`);
        return this.page.getByTestId(
            PastoralRecord.DATA_TEST_PASTORAL_ACTION_STATUS.replace("{actionId}", actionId.toString()),
        );
    }

    /**
     * Get the completion meta element for a completed action
     * @param actionId - The action id
     * @returns The completion meta locator
     */
    getActionCompletedMeta(actionId: number): Locator {
        return this.page.locator(`[data-action-status-${actionId}]`).locator(`span[class="meta"]`);
    }

    /**
     * Get the completed by user link for a completed action
     * @param actionId - The action id
     * @returns The completed by user link locator
     */
    getActionCompletedByLink(actionId: number): Locator {
        return this.page.locator(`[data-action-status-${actionId}]`).locator("a.profile-img");
    }
}
