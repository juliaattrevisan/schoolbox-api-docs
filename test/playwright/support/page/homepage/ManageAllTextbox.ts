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
    async clickManageAll(): Promise<void> {
        await this.page.locator("a.button").getByText("Manage All").click();
        await this.page.waitForSelector(`a[data-action="addTextbox"]`, { state: "visible" });
    }

    /**
     * click Create Text Box button
     */
    async clickCreateTextBox(): Promise<void> {
        await this.page.waitForSelector("a.icon-add.button");
        await this.page.click("a.icon-add.button");
    }

    /**
     * type a heading text
     * @param textBoxItem - The textbox item number
     * @param headingText - The heading text
     */
    async typeTextBoxHeading(textBoxItem: number, headingText: string): Promise<void> {
        await this.page.waitForSelector(`input[name="textbox[${textBoxItem}][heading]"]`);
        await this.page.locator(`input[name="textbox[${textBoxItem}][heading]"]`).fill(headingText);
    }

    /**
     * enter a textbox content
     * @param textBoxItem - The textbox item number
     * @param contentText - The content text
     */
    async typeTextBoxContent(textBoxItem: number, contentText: string): Promise<void> {
        const descriptionTextarea = this.page.locator(`#textbox-${textBoxItem}-body`);
        await this.tester.fillCKEditorByLocator(descriptionTextarea, contentText);
    }

    /**
     * click Save All button
     */
    async clickSaveAll(): Promise<void> {
        await this.page.click("input[type=submit]");
        await this.page.waitForTimeout(2000);
    }
}
