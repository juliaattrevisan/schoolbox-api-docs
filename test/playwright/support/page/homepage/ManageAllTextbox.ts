import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { Page } from "@playwright/test";

export default class ManageAllTextbox {
    private tester: AcceptanceTester;

    private page: Page;

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * click Manage All button on the textbox component
     */
    async clickManageAll() {
        await this.page.locator("a.button").getByText("Manage All").click();
        await this.page.waitForSelector(`a[data-action="addTextbox"]`, { state: "visible" });
    }

    /**
     * click Create Text Box button
     */
    async clickCreateTextBox() {
        await this.page.click("a.icon-add.button");
    }

    /**
     * type a heading text
     * @param { number } textBoxItem - The textbox item number
     * @param { string } headingText - The heading text
     */
    async typeTextBoxHeading(textBoxItem: number, headingText: string) {
        await this.page.fill(`input[name="textbox[${textBoxItem}][heading]"]`, headingText);
    }

    /**
     * enter a textbox content
     * @param { number } textBoxItem - The textbox item number
     * @param { string } contentText - The content text
     */
    async typeTextBoxContent(textBoxItem: number, contentText: string) {
        // await this.page.fill(`#textbox-${textBoxItem}-body`, contentText);
        // await this.tester.fillCKEditorByLocator(`#textbox-${textBoxItem}-body`, contentText);

        const descriptionTextarea = this.page.locator(`#textbox-${textBoxItem}-body`);
        await this.tester.fillCKEditorByLocator(descriptionTextarea, contentText);

        // const descriptionTextarea = this.page.getByTestId(`component-textbox-${textBoxItem}`);
        // await this.tester.fillCKEditorByLocator(descriptionTextarea, description);
    }

    /**
     * click Save All button
     */
    async clickSaveAll() {
        await this.page.click("input[type=submit]");
    }
}
