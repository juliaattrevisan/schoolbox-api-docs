import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { expect } from "@schoolboxTest/playwright/support/integrations";

export default class AgentChatPrompt {
    static readonly DATA_TEST_PROMPT_CONTAINER = "ac-prompt";
    static readonly DATA_TEST_PROMPT_TEXTAREA = "ac-prompt-textarea";
    static readonly DATA_TEST_PROMPT_SEND_BUTTON = "ac-prompt-send-button";

    constructor(
        private page: Page,
        private tester: AcceptanceTester,
    ) {}

    /**
     * Type a message into the prompt textarea
     * @param text - The text to type
     */
    public async typePrompt(text: string): Promise<void> {
        const textarea = this.page.getByTestId(AgentChatPrompt.DATA_TEST_PROMPT_TEXTAREA);
        await textarea.fill(text);
    }

    /**
     * Click the send button to submit the prompt
     */
    public async send(): Promise<void> {
        const sendButton = this.page.getByTestId(AgentChatPrompt.DATA_TEST_PROMPT_SEND_BUTTON);
        await sendButton.click();
    }

    /**
     * Verify that the prompt textarea is empty
     */
    public async expectPromptEmpty(): Promise<void> {
        const textarea = this.page.getByTestId(AgentChatPrompt.DATA_TEST_PROMPT_TEXTAREA);
        await expect(textarea).toBeEmpty();
    }
}
