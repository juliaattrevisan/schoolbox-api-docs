import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { Page, expect } from "@playwright/test";

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
