import type { Locator, Page } from "@playwright/test";
import { assertUnreachable } from "@js/util/errorUtils";
import { AgentChatMessageType } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { expect } from "@schoolboxTest/playwright/support/integrations";

/**
 * Support file for the Agent Chat Feed.
 * This is used both when talking to the agent and when viewing the conversation later (read-only)
 */
export default class AgentChatFeed {
    static readonly DATA_TEST_FEED_CONTAINER = "ac-feed";
    static readonly DATA_TEST_FEED_REQUEST = "ac-feed-request-list-item"; // append -{entryKey}
    static readonly DATA_TEST_FEED_RESPONSE = "ac-feed-response-list-item"; // append -{entryKey}
    static readonly DATA_TEST_FEED_RESPONSE_LOADING = "ac-feed-response-loading";
    static readonly DATA_TEST_FEED_RESPONSE_RETRY = "ac-feed-response-retry-button";
    static readonly DATA_TEST_FEED_ALERT = "ac-feed-alert-list-item"; // append -{entryKey}
    static readonly DATA_TEST_FEED_MESSAGE_CONTENT = "ac-feed-message-content";

    constructor(
        private page: Page,
        private tester: AcceptanceTester,
    ) {}

    /**
     * @returns Locator for the container of the entire feed
     */
    public locateFeed(): Locator {
        return this.page.getByTestId(AgentChatFeed.DATA_TEST_FEED_CONTAINER);
    }

    /**
     * @param type - The type of message to locate
     * @returns The data-test selector to use for messages of the given type
     */
    public static dataTestForType(type: AgentChatMessageType): string {
        if (type === AgentChatMessageType.REQUEST) return AgentChatFeed.DATA_TEST_FEED_REQUEST;
        if (type === AgentChatMessageType.RESPONSE) return AgentChatFeed.DATA_TEST_FEED_RESPONSE;
        if (type === AgentChatMessageType.ALERT) return AgentChatFeed.DATA_TEST_FEED_ALERT;

        return assertUnreachable(type);
    }

    /**
     * @param type - The type of messages to locate
     * @returns Locator for all messages in the feed of the given type
     */
    public locateMessagesWithType(type: AgentChatMessageType): Locator {
        const dataTestPrefix = AgentChatFeed.dataTestForType(type);
        const matchingDataTests = new RegExp(`^${dataTestPrefix}-`);
        return this.locateFeed().getByTestId(matchingDataTests);
    }

    /**
     * @returns Locator for all messages in the feed
     */
    public locateAllMessages(): Locator {
        return this.locateMessagesWithType(AgentChatMessageType.REQUEST)
            .or(this.locateMessagesWithType(AgentChatMessageType.RESPONSE))
            .or(this.locateMessagesWithType(AgentChatMessageType.ALERT));
    }

    /**
     * Verify that a particular message contains given text
     * @param messageLocator - Locator for the message to check
     * @param expectedText - Text expected to be found within the message
     */
    public static async expectMessageToHaveText(messageLocator: Locator, expectedText: string): Promise<void> {
        const contentLocator = messageLocator.getByTestId(AgentChatFeed.DATA_TEST_FEED_MESSAGE_CONTENT);
        await expect(contentLocator).toContainText(expectedText);
    }

    /**
     * Verify that a particular message is of a given type
     * @param messageLocator - Locator for the message to check
     * @param type - The type expected for the message
     */
    public static async expectMessageIsType(messageLocator: Locator, type: AgentChatMessageType): Promise<void> {
        const dataTest = await messageLocator.getAttribute("data-test");
        if (dataTest === null) {
            throw new Error("Message locator is missing a data-test attribute");
        }
        const matchingDataTestPrefix = AgentChatFeed.dataTestForType(type);
        const matchingDataTests = new RegExp(`^${matchingDataTestPrefix}-`);
        expect(dataTest).toMatch(matchingDataTests);
    }

    /**
     * Verify that the opening message in the feed matches the given text
     * @param expectedText - The expected opening message text
     */
    public async expectOpeningMessageToHaveText(expectedText: string): Promise<void> {
        const firstMessage = this.locateAllMessages().first();
        await AgentChatFeed.expectMessageIsType(firstMessage, AgentChatMessageType.RESPONSE);
        await AgentChatFeed.expectMessageToHaveText(firstMessage, expectedText);
    }

    /**
     * Expect that a message in the feed is currently loading
     * @param messageLocator - Locator for the message to check
     */
    public static async expectMessageIsLoading(messageLocator: Locator): Promise<void> {
        const loadingIndicator = messageLocator.getByTestId(AgentChatFeed.DATA_TEST_FEED_RESPONSE_LOADING);
        await expect(loadingIndicator).toBeVisible();
    }

    /**
     * Wait for a message to no longer be loading
     * @param messageLocator - Locator for the message to check
     */
    public static async waitForMessageLoaded(messageLocator: Locator): Promise<void> {
        const loadingIndicator = messageLocator.getByTestId(AgentChatFeed.DATA_TEST_FEED_RESPONSE_LOADING);
        await expect(loadingIndicator).not.toBeVisible();
    }

    /**
     * Click the retry button on a given message (should always be the most recent)
     * @param messageLocator - Locator for the response message that has a retry button
     */
    public static async clickRetry(messageLocator: Locator): Promise<void> {
        const retryButton = messageLocator.getByTestId(AgentChatFeed.DATA_TEST_FEED_RESPONSE_RETRY);
        await retryButton.click();
    }
}
