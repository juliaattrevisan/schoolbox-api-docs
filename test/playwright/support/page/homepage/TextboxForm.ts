import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";

export default class TextboxForm {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/homepage/{homepageId}/textBoxCreate/{componentId}#go-to{componentId}";

    static readonly FORM = 'form[id^="textBoxForm"]';

    static readonly FORM_SAVE = `${TextboxForm.FORM} input[type="submit"]`;

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    async fillHeading(heading: string): Promise<void> {
        await this.page.waitForSelector('input[name="heading"]');
        await this.page.locator('input[name="heading"]').fill(heading);
    }

    async seeInHeading(heading: string): Promise<void> {
        await expect(this.page.locator(`${TextboxForm.FORM} input[name="textBoxHeading"]`)).toHaveValue(heading);
    }

    async fillContent(description: string, textBoxItem: number): Promise<void> {
        const descriptionTextarea = this.page.getByTestId(`component-textbox-${textBoxItem}`);
        await this.tester.fillCKEditorByLocator(descriptionTextarea, description);
    }

    /**
     * Click the template icon in the CKEditor5 bar and selects a template based on the template name
     * @param templateName - The name of the template to select
     */
    async clickEditorTemplateCKEditor(templateName: string): Promise<void> {
        // wait for the ckeditor to be visible
        await expect(this.page.locator("div.ck.ck-editor").first()).toHaveCount(1);

        // click the template icon in the ckeditor toolbar
        await this.page.locator(`button[data-cke-tooltip-text="Insert template"]`).click();

        // select a template
        await this.page.locator("li.ck.ck-list__item").getByText(templateName).click();
    }

    async seeInContent(content: string): Promise<this> {
        const id = await this.page.locator(`${TextboxForm.FORM} textarea[name^="textBoxBody"]`).getAttribute("id");
        await expect(this.page.locator(`#${id}`)).toHaveText(content);
        return this;
    }

    async submit(): Promise<void> {
        await this.page.click('input[type=submit][value="Create Text Box"]');
        await this.page.waitForTimeout(2000);
    }

    async modify(): Promise<void> {
        await this.page
            .locator('div[class="component-container Schoolbox_Resource_Textbox_Component_Homepage_Controller"]')
            .scrollIntoViewIfNeeded();
        await this.page.locator('button[data-action="modify"]').click();
        await this.page.waitForTimeout(2000);
    }

    async save(): Promise<void> {
        await this.page
            .locator('div[class="component-container Schoolbox_Resource_Textbox_Component_Homepage_Controller"]')
            .scrollIntoViewIfNeeded();
        await this.page.locator('button[data-action="save"]').click();
        await this.page.waitForTimeout(2000);
    }
}
