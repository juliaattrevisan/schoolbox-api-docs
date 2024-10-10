import { Page } from "@playwright/test";

export default class QuestionBankPage {
    private page: Page;

    static readonly URL = "/survey/questionbank/contents/{category_id}/{assess_id}/{section_id}";

    static readonly CATEGORY_ELECTIVE_SELECTION = 7;

    static readonly CATEGORY_MEDICAL = 9;

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Generates route for this page.
     * @param assessId - The ID of the page
     */
    async goto(assessId: number): Promise<void> {
        await this.page.goto(QuestionBankPage.generateRoute(assessId));
    }

    /**
     * Generates route for this page
     * @param assessId - The ID of the page
     * @returns - the URL for the page
     */
    public static generateRoute(assessId: number): string {
        return QuestionBankPage.URL.replace("{assessId}", assessId.toString());
    }

    /**
     * Selects question bank category
     * @param categoryId - The ID of the category
     */
    async clickCategory(categoryId: number): Promise<void> {
        await this.page.click(`#node_${categoryId}_anchor`);
        await this.page.locator(`h2.subheader:has-text("ELECTIVE SELECTION")`).isVisible({ timeout: 30000 });
    }

    /**
     * Clicks modify button of a question in question bank
     * @param question - The question to modify
     */
    async clickModify(question: string): Promise<void> {
        const questionListItem = this.page
            .locator("#questionBankForm > section > ul > li")
            .filter({ hasText: question });
        await questionListItem.locator("nav > a.icon-modify").click();
    }
}
