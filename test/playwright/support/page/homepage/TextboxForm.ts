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

    async fillHeading(heading: string) {
        await this.page.waitForSelector('input[name="heading"]');
        await this.page.locator('input[name="heading"]').fill(heading);

        return this;
    }

    async seeInHeading(heading: string) {
        await expect(this.page.locator(`${TextboxForm.FORM} input[name="textBoxHeading"]`)).toHaveValue(heading);
        return this;
    }

    async fillContent(description: string, textBoxItem: number) {
        const descriptionTextarea = this.page.getByTestId(`component-textbox-${textBoxItem}`);
        await this.tester.fillCKEditorByLocator(descriptionTextarea, description);
    }

    async seeInContent(content: string) {
        const id = await this.page.locator(`${TextboxForm.FORM} textarea[name^="textBoxBody"]`).getAttribute("id");
        await expect(this.page.locator(`#${id}`)).toHaveText(content);
        return this;
    }

    async submit() {
        await this.page.click('input[type=submit][value="Create Text Box"]');
        await this.page.waitForTimeout(2000);
        return this;
    }

    async modify() {
        await this.page
            .locator('div[class="component-container Schoolbox_Resource_Textbox_Component_Homepage_Controller"]')
            .scrollIntoViewIfNeeded();
        await this.page.locator('button[data-action="modify"]').click();
        return this;
    }

    async save() {
        await this.page
            .locator('div[class="component-container Schoolbox_Resource_Textbox_Component_Homepage_Controller"]')
            .scrollIntoViewIfNeeded();
        await this.page.locator('button[data-action="save"]').click();
        return this;
    }
}
