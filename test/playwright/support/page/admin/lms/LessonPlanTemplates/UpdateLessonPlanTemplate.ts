import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page } from "@playwright/test";

export default class UpdateLessonPlanTemplate {
    private page: Page;

    private tester: AcceptanceTester;

    static readonly TEMPLATE_FIELD = "input#field-title-";

    constructor(page: Page, tester: AcceptanceTester) {
        this.page = page;
        this.tester = tester;
    }

    /**
     * type a field title
     * @param templateFieldNumber - the number of the field
     * @param templateFieldTitle - the title of the template
     */
    async typeFieldTile(templateFieldNumber: number, templateFieldTitle: string): Promise<void> {
        const templateField = `input#field-title-${templateFieldNumber}`;
        await this.page.waitForSelector(templateField);
        await this.page.fill(templateField, templateFieldTitle);
    }

    /**
     * type a field body description
     * @param templateFieldNumber - the number of the field
     * @param body - the body text for the template
     */
    async fillBody(templateFieldNumber: number, body: string): Promise<void> {
        // await this.page.fillRichTextField(`field-body-${templateFieldNumber}`, body);
        await this.tester.fillCKEditorById(`field-body-${templateFieldNumber}`, body);
    }
}
