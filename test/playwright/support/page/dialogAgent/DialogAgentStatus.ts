import { keysOf } from "@js/util/arrayUtils";
import type { Locator, Page } from "@playwright/test";
import { STATUS_LABELS } from "@schoolboxAssessment/Types/dialogAgent";
import type { DialogAgentResponseStatus } from "@schoolboxAssessment/Types/dialogAgent";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { expect } from "@schoolboxTest/playwright/support/integrations";

export enum DialogAgentStatusAction {
    VIEW = "view",
    SUMMARY = "summary",
    SUBMIT = "submit",
    RESET = "reset",
    REOPEN = "reopen",
    ASSISTANCE_RESOLVE = "assistanceResolve",
}

export default class DialogAgentStatus {
    static readonly DATA_TEST_TITLE = "assessment-status-meta-title";
    static readonly DATA_TEST_GRID_ROW = "das-grid-row";
    static readonly DATA_TEST_GRID_ROW_USER_NAME = "das-grid-row-user-name";
    static readonly DATA_TEST_GRID_ROW_STATUS_META = "das-grid-row-status-meta";

    static readonly DATA_TEST_GRID_ROW_ACTION_MENU = "sbx-pop-out-response-options-"; // append {participantId}-button
    static readonly DATA_TEST_ACTION_MENU_CONTENT_ACTION = "das-actions-"; // append {DialogAgentStatusAction}
    static readonly DATA_TEST_ACTION_MODAL_CONFIRM = "das-actions-modal-confirm";
    static readonly SELECTOR_ACTION_MENU_CONTENT = "response-options-"; // append {participantId}

    constructor(
        private page: Page,
        private tester: AcceptanceTester,
    ) {}

    /**
     * Verify that we are on the dialog agent status page
     * @param activityTitle - optionally specify the title to verify the page for a specific activity
     */
    public async assertIsOnStatusPage(activityTitle?: string): Promise<void> {
        const titleContainer = this.page.getByTestId(DialogAgentStatus.DATA_TEST_TITLE);
        await expect(titleContainer).toBeVisible();

        if (activityTitle) {
            await expect(titleContainer).toHaveText(`Status of ${activityTitle}`);
        }
    }

    /**
     * @param name - The full name of the participant
     * @returns A Locator for a particular participant's row in the status grid
     */
    public locateGridRowForParticipant(name: string): Locator {
        const matchingUserLocator = this.page.getByTestId(DialogAgentStatus.DATA_TEST_GRID_ROW_USER_NAME).filter({
            hasText: name,
        });

        return this.page.getByTestId(DialogAgentStatus.DATA_TEST_GRID_ROW).filter({ has: matchingUserLocator });
    }

    /**
     * Validate the status for a particular participant in the status grid
     * @param participantRow - The Locator to narrow down to a particular participant row
     * @param expectedStatus - The expected status that should be shown
     */
    public static async expectParticipantStatus(
        participantRow: Locator,
        expectedStatus: DialogAgentResponseStatus,
    ): Promise<void> {
        const statusMeta = participantRow.getByTestId(DialogAgentStatus.DATA_TEST_GRID_ROW_STATUS_META);
        await expect(statusMeta).toHaveText(STATUS_LABELS[expectedStatus]);
    }

    /**
     * Click the button to open/close the 3-dot action menu for a particular participant in the status grid
     * @param participantRow - The Locator to narrow down to a particular participant row
     */
    public static async clickParticipantActionMenu(participantRow: Locator): Promise<void> {
        const actionButtonDataTest = new RegExp(`^${DialogAgentStatus.DATA_TEST_GRID_ROW_ACTION_MENU}\\d+-button$`);
        const actionMenuButton = participantRow.getByTestId(actionButtonDataTest);
        await actionMenuButton.click();
    }

    /**
     * Get Locators for all possible actions in an opened actions 3-dot menu
     * @param participantRow - The Locator to narrow down to a particular participant row
     * @returns A map of DialogAgentStatusAction to a Locator (which may or may not be present)
     */
    public async locateActionMenuOptions(participantRow: Locator): Promise<Record<DialogAgentStatusAction, Locator>> {
        // derive the ID of the dropdown content based on the button which includes the participant ID
        const actionButtonDataTest = new RegExp(`^${DialogAgentStatus.DATA_TEST_GRID_ROW_ACTION_MENU}(\\d+)-button$`);
        const actionMenuButton = participantRow.getByTestId(actionButtonDataTest);
        const buttonDataTestId = await actionMenuButton.getAttribute("data-test");

        const participantId = buttonDataTestId?.match(actionButtonDataTest)?.[1] ?? null;
        if (!participantId) {
            expect(participantId, "Unable to find participant ID from action menu button").not.toBeNull();
            throw new Error("Unable to find participant ID from action menu button");
        }

        // Find the relevant action button
        const dropdownContent = this.page.locator(`#${DialogAgentStatus.SELECTOR_ACTION_MENU_CONTENT}${participantId}`);

        const actionLocators = {} as Record<DialogAgentStatusAction, Locator>;
        for (const action of Object.values(DialogAgentStatusAction)) {
            const actionDataTest = `${DialogAgentStatus.DATA_TEST_ACTION_MENU_CONTENT_ACTION}${action}`;
            actionLocators[action] = dropdownContent.getByTestId(actionDataTest);
        }

        return actionLocators;
    }

    /**
     * Click a particular action in an opened actions 3-dot menu
     * @param participantRow - The Locator to narrow down to a particular participant row
     * @param action - The action to click
     */
    public async clickActionMenuOption(participantRow: Locator, action: DialogAgentStatusAction): Promise<void> {
        const actionOptions = await this.locateActionMenuOptions(participantRow);
        await actionOptions[action].click();
    }

    /**
     * Expect ONLY the specified actions to be visible in an opened actions 3-dot menu
     * @param participantRow - The Locator to narrow down to a particular participant row
     * @param actions - The actions that should be visible (all other actions will be expected to not be visible)
     */
    public async expectActionMenuOptions(participantRow: Locator, actions: DialogAgentStatusAction[]): Promise<void> {
        const actionOptions = await this.locateActionMenuOptions(participantRow);

        for (const action of keysOf(actionOptions)) {
            if (actions.includes(action)) {
                await expect(actionOptions[action]).toBeVisible();
            } else {
                await expect(actionOptions[action]).not.toBeVisible();
            }
        }
    }

    /**
     * Click the proceed/confirm button when a specific action requires further confirmation in a modal
     */
    public async clickActionConfirm(): Promise<void> {
        await this.page.getByTestId(DialogAgentStatus.DATA_TEST_ACTION_MODAL_CONFIRM).click();
    }
}
