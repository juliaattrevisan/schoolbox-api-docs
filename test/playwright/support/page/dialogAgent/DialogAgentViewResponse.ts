import type { Locator, Page } from "@playwright/test";
import { STATUS_LABELS } from "@schoolboxAssessment/Types/dialogAgent";
import type { DialogAgentResponseStatus } from "@schoolboxAssessment/Types/dialogAgent";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { expect } from "@schoolboxTest/playwright/support/integrations";
import type { DialogAgentStatusAction } from "./DialogAgentStatus";

export default class DialogAgentViewResponse {
    static readonly DATA_TEST_CONTAINER = "dialog-agent-view-response-container";
    static readonly DATA_TEST_PARTICIPANT_CAROUSEL_ROW = "participant-carousel-row";
    static readonly DATA_TEST_PARTICIPANT_CAROUSEL_ROW_NAME = "participant-carousel-row-name";
    static readonly DATA_TEST_PARTICIPANT_CAROUSEL_ROW_STATUS = "participant-carousel-row-status";
    static readonly DATA_TEST_ACTION_MENU = "sbx-pop-out-header-options-button";
    static readonly DATA_TEST_ACTION_MENU_CONTENT_ACTION = "das-actions-"; // append {DialogAgentStatusAction}

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

    /**
     * @param name - The full name of the participant
     * @returns A Locator for a particular participant's row in the participant carousel
     */
    public locateCarouselRowForParticipant(name: string): Locator {
        const matchingNameLocator = this.page
            .getByTestId(DialogAgentViewResponse.DATA_TEST_PARTICIPANT_CAROUSEL_ROW_NAME)
            .filter({ hasText: name });

        return this.page
            .getByTestId(DialogAgentViewResponse.DATA_TEST_PARTICIPANT_CAROUSEL_ROW)
            .filter({ has: matchingNameLocator });
    }

    /**
     * Validate the status for a particular participant by name
     * @param participantRow - The Locator to narrow down to a particular participant row
     * @param expectedStatus - The expected status that should be shown
     */
    public static async expectParticipantStatus(
        participantRow: Locator,
        expectedStatus: DialogAgentResponseStatus,
    ): Promise<void> {
        const statusTxt = participantRow.getByTestId(DialogAgentViewResponse.DATA_TEST_PARTICIPANT_CAROUSEL_ROW_STATUS);
        await expect(statusTxt).toHaveText(STATUS_LABELS[expectedStatus]);
    }

    /**
     * Click the button to open/close the 3-dot action menu
     */
    public async clickActionMenu(): Promise<void> {
        const actionMenuButton = this.page.getByTestId(DialogAgentViewResponse.DATA_TEST_ACTION_MENU);
        await actionMenuButton.click();
    }

    /**
     * Click a particular action in the action menu
     * @param action - The action to click
     */
    public async clickActionMenuOption(action: DialogAgentStatusAction): Promise<void> {
        const actionDataTest = `${DialogAgentViewResponse.DATA_TEST_ACTION_MENU_CONTENT_ACTION}${action}`;
        await this.page.getByTestId(actionDataTest).click();
    }
}
