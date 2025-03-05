import type { Page } from "@playwright/test";
import { expect } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class QuizContentsPage {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "https://vagrant.dev.schoolbox.cloud/learning/quiz/{quiz_id}/content";

    // Constants for selectors - these are from learnosity and we will need to get updated in case they update it iin their end
    static readonly ITEM_BUTTON = "span.lrn-author-button-text";

    static readonly ADD_NEW_BUTTON = "button.lrn-author-widget-button";

    static readonly SAVE_BUTTON = "button.lrn-btn-save.lrn-btn.lrn-btn-legacy.lrn-btn-primary.lrn-btn-primary-legacy";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     *
     * Click Create Item button
     *
     */
    async clickCreateItemButton(): Promise<void> {
        await expect(this.page.locator(QuizContentsPage.ITEM_BUTTON).getByText("Create item")).toBeVisible();
        await this.page.locator(QuizContentsPage.ITEM_BUTTON).getByText("Create item").click();
        // adding a timeout to avoid waiting for something that is from learnosity side
        await this.page.waitForTimeout(1000);
    }

    /**
     *
     * Click Add new button
     *
     */
    async clickAddNewButton(): Promise<void> {
        await expect(this.page.locator(QuizContentsPage.ADD_NEW_BUTTON)).toBeVisible();
        await this.page.locator(QuizContentsPage.ADD_NEW_BUTTON).click();
        // adding a timeout to avoid waiting for something that is from learnosity side
        await this.page.waitForTimeout(1000);
    }

    /**
     *
     * Click one of the items question type to bee added: Standard Multiple choice question. , True or false, etc
     * @param questionType - The question type to be added
     */
    async clickQuestionType(questionType: string): Promise<void> {
        await expect(this.page.getByLabel(questionType)).toBeVisible();
        await this.page.getByLabel(questionType).click();
        // adding a timeout to avoid waiting for something that is from learnosity side
        await this.page.waitForTimeout(1000);
    }

    /**
     *
     * Click Save button
     *
     */
    async clickSaveButton(): Promise<void> {
        await this.page.locator(QuizContentsPage.SAVE_BUTTON).first().click();
        // adding a timeout to avoid waiting for something that is from learnosity side
        await this.page.waitForTimeout(1000);
    }
}
