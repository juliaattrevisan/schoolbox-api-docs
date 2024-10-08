import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page } from "@playwright/test";

export default class CreateLessonPlanTemplate {
    private page: Page;

    private tester: AcceptanceTester;

    static readonly URL = "/adminv2/lesson-plan/template/create";

    static readonly TEMPLATE_NAME = "#lesson-plan-template-title";

    static readonly ADD_NEW_FIELD = "#add-field";

    static readonly BUTTON_SAVE = "#save-button";

    static readonly BUTTON_DELETE = "a.button.icon-delete";

    static readonly DATA_TEST_CONFIRM_YES = "confirm-dialog-confirm-button";

    constructor(page: Page, tester: AcceptanceTester) {
        this.page = page;
        this.tester = tester;
    }

    /**
     * type a lesson plan template title
     * @param templateName - the name of the template
     */
    async typeName(templateName: string): Promise<void> {
        await this.page.waitForSelector(CreateLessonPlanTemplate.TEMPLATE_NAME);
        await this.page.fill(CreateLessonPlanTemplate.TEMPLATE_NAME, templateName);
    }

    /**
     * click 'Add a new field"
     */
    async clickAddNewFieldButton(): Promise<void> {
        await this.page.waitForSelector(CreateLessonPlanTemplate.ADD_NEW_FIELD);
        await this.page.click(CreateLessonPlanTemplate.ADD_NEW_FIELD);
    }

    /**
     * type a field title
     * @param templateFieldNumber - the number of the field
     * @param templateFieldTitle - the title of the template
     */
    async typeFieldTile(templateFieldNumber: number, templateFieldTitle: string): Promise<void> {
        const templateField = `input#field-title-new${templateFieldNumber}`;
        await this.page.waitForSelector(templateField);
        await this.page.fill(templateField, templateFieldTitle);
    }

    /**
     * type a field body description
     * @param templateFieldNumber - the number of the field
     * @param body - the body text for the template
     */
    async fillBody(templateFieldNumber: number, body: string): Promise<void> {
        await this.tester.fillCKEditorById(`field-body-new${templateFieldNumber}`, body);
    }

    /**
     * checkbox field visible to students when updating a template
     * @param templateFieldNumber - the number of the field
     */
    async clickCheckboxVisibleStudentsCreatingTemplate(templateFieldNumber: number): Promise<void> {
        await this.page.click(`label[for="field-visibility-new${templateFieldNumber}"]`);
    }

    /**
     * checkbox field visible to students when updating a template
     * @param templateFieldNumber - the number of the field
     */
    async clickCheckboxVisibleStudentsUpdatingTemplate(templateFieldNumber: number): Promise<void> {
        await this.page.click(`label[for="field-new-visibility-${templateFieldNumber}"]`);
    }

    /**
     * click SAVE button
     */
    async clickSaveButton(): Promise<void> {
        await this.page.click(CreateLessonPlanTemplate.BUTTON_SAVE);
    }

    /**
     * click delete button
     */
    async clickDeleteButton(): Promise<void> {
        await this.page.click(CreateLessonPlanTemplate.BUTTON_DELETE);
    }

    /**
     * confirm to delete a field: click yes in the confirmation dialog
     */
    async clickConfirmDeleteButton(): Promise<void> {
        await this.page.getByTestId(CreateLessonPlanTemplate.DATA_TEST_CONFIRM_YES).last().click();
    }

    /**
     * create a Lesson Plan Template
     * @param templateName - the name of the template
     */
    async createLessonPlanTemplate(templateName: string): Promise<void> {
        await this.typeName(templateName);
        await this.clickSaveButton();
    }
}
