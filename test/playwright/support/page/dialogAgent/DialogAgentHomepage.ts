import { expect } from "@playwright/test";
import type { Locator, Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";

/**
 * Support file for the Dialog Agent Homepage Component
 */
export default class DialogAgentHomepage {
    static readonly HOMEPAGE_ADD_COMPONENT_SECTION = Homepage.ELEMENT_ADD_COMPONENT_SECTION_LEARNING;
    static readonly HOMEPAGE_COMPONENT_NAME = "Discussions"; // Terminology for Dialog Agent is "Discussions"
    static readonly EMPTY_STATE_MESSAGE_AI_DISABLED = "The Discussion feature is currently disabled";

    static readonly DATA_TEST_EMPTY_CREATE_BUTTON = "component-dialogAgent-empty-create-button";

    constructor(
        private page: Page,
        private tester: AcceptanceTester,
        private component: Locator,
    ) {}

    /**
     * Verify that the empty state message indicates the feature is disabled
     */
    async verifyEmptyStateDisabled(): Promise<void> {
        await expect(this.component).toContainText(DialogAgentHomepage.EMPTY_STATE_MESSAGE_AI_DISABLED);
    }

    /**
     * Verify that the empty state create button is not on the page
     */
    async verifyEmptyStateCreateButtonNotVisible(): Promise<void> {
        const createButton = this.component.getByTestId(DialogAgentHomepage.DATA_TEST_EMPTY_CREATE_BUTTON);
        await expect(createButton).not.toBeVisible();
    }

    /**
     * Click the empty state create button
     */
    async clickEmptyStateCreateButton(): Promise<void> {
        const createButton = this.component.getByTestId(DialogAgentHomepage.DATA_TEST_EMPTY_CREATE_BUTTON);
        await createButton.click();
    }
}
