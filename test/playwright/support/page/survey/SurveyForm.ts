import { Page } from "@playwright/test";

export default class SurveyForm {
    private page: Page;

    static readonly URL = "/survey/create/{class_id}";

    static readonly FORM = "#createAssess";

    static readonly SELECTOR_TITLE = `${SurveyForm.FORM} input[name="title"]`;

    static readonly SELECTOR_OPEN_DATE = `${SurveyForm.FORM} input[name="openDate"]`;

    static readonly SELECTOR_DUE_DATE = `${SurveyForm.FORM} input[name="dueDate"]`;

    static readonly SELECTOR_BUTTON_CREATE = `input[type=submit][value="Create"]`;

    static readonly SELECTOR_BUTTON_UPDATE = `${SurveyForm.FORM} [type=submit][value="Update"]`;

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Generates route for this page.
     * @param classId - The ID of the page
     */
    async goto(classId: number): Promise<void> {
        await this.page.goto(SurveyForm.generateRoute(classId));
    }

    /**
     * Generates route for this page
     * @param classId - The ID of the page
     * @returns - the URL for the page
     */
    public static generateRoute(classId: number): string {
        return SurveyForm.URL.replace("{class_id}", classId.toString());
    }

    /**
     * Sets title
     * @param title - The title of the survey
     */
    async setTitle(title: string): Promise<void> {
        await this.page.locator(`h1:has-text("CREATE SURVEY")`).isVisible();
        await this.page.fill(SurveyForm.SELECTOR_TITLE, title);
    }

    /**
     * Sets open date
     * @param openDate - The open date of the survey
     */
    async setOpenDate(openDate: string): Promise<void> {
        await this.page.fill(SurveyForm.SELECTOR_OPEN_DATE, openDate);
    }

    /**
     * Submits this form to create a survey
     */
    async create(): Promise<void> {
        await this.page.click(SurveyForm.SELECTOR_BUTTON_CREATE);
    }

    /**
     * Submits this form to update a survey
     */
    async update(): Promise<void> {
        await this.page.click(SurveyForm.SELECTOR_BUTTON_UPDATE);
    }
}
