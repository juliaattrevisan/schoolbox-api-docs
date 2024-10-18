import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type DatabaseHelper from "../../DatabaseHelper";

export default class QuestionForm {
    private page: Page;

    private tester: AcceptanceTester;

    private databaseHelper: DatabaseHelper;

    static readonly URL = "/assess/question/modify/{question_id}";

    static readonly FORM = "#createQuestionForm";

    static readonly SELECTOR_TITLE = `input[name="title"]`;

    static readonly SELECTOR_BUTTON_CREATE = `[type=submit][value="Add Question"]`;

    static readonly SELECTOR_BUTTON_MODIFY = `[type=submit][value="Modify"]`;

    static readonly SELECTOR_TYPE_ID = 'select[name="type_id"]';

    static readonly SELECTOR_SECTION_ID = 'input[name="section_id"]';

    static readonly SELECTOR_DESCRIPTION = "#question + div.ck";

    static readonly TYPE_YESNO = 10;

    static readonly TYPE_SHORT_SIGNATURE = 16;

    static readonly TYPE_SHORT_ANSWER = 19;

    constructor(page: Page, tester: AcceptanceTester, db: DatabaseHelper) {
        this.page = page;
        this.tester = tester;
        this.databaseHelper = db;
    }

    /*
     * Sets question type
     * @param type - The type of the question
     */
    async setType(type: number): Promise<void> {
        await expect(this.page.locator(`h2.subheader:has-text("CREATE QUESTION")`)).toBeVisible();
        await this.page.selectOption(QuestionForm.SELECTOR_TYPE_ID, type.toString());
        await expect(this.page.locator(QuestionForm.SELECTOR_DESCRIPTION)).toBeVisible();
    }

    /*
     * Sets question
     * @param question - The question
     */

    async setQuestion(question: string): Promise<void> {
        await this.tester.fillCKEditorById("question", question);
    }

    /*
     * Submits this form to create a question
     */
    async create(): Promise<void> {
        await this.page.click(QuestionForm.SELECTOR_BUTTON_CREATE);
        await expect(this.page.locator(`h2.subheader:has-text("CREATE QUESTION")`)).toBeVisible();
    }

    /*
     * Submits this form to modify a question bank
     */
    async modify(): Promise<void> {
        await this.page.locator(QuestionForm.SELECTOR_BUTTON_MODIFY).isEnabled();
        await this.page.click(QuestionForm.SELECTOR_BUTTON_MODIFY);
        await expect(this.page.locator(`h1:has-text("Question Bank")`)).toBeVisible();
    }

    /*
     * Gets section id of a quiz just created
     * @returns - The section ID
     */
    async getSectionId(): Promise<number> {
        const sectionIdString = await this.page.locator(QuestionForm.SELECTOR_SECTION_ID).getAttribute("value");
        const sectionId = Number(sectionIdString);
        return sectionId;
    }

    /*
     * Gets question id from question type and question
     * @param type - The type of the question
     * @param question - The question
     * @returns - The question ID
     * */
    async getQuestionId(type: number, sectionId: number): Promise<number> {
        const questionIdString = await this.databaseHelper.grabColumnFromDatabase("assess_question", "question_id", {
            type_id: type,
            section_id: sectionId,
        });
        const questionId = Number(questionIdString);
        return questionId;
    }
}
