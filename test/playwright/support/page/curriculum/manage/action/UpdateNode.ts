import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class UpdateNode {
    private page: Page;

    private tester: AcceptanceTester;

    static readonly NAME_FIELD = "#name";

    static readonly DISPLAY_TOGGLE = "Update ";

    static readonly CODE_FIELD = "#code";

    static readonly DESCRIPTION_FIELD = "description-";

    static readonly DATATEST_UPDATE_BUTTON = "update-node";

    constructor(page: Page, tester: AcceptanceTester) {
        this.page = page;
        this.tester = tester;
    }

    /**
     * select the display button
     * @param value - The locator of the node
     */
    async clickDisplay(value: string): Promise<void> {
        await this.page.getByRole("button", { name: `${UpdateNode.DISPLAY_TOGGLE}${value}` }).click();
    }

    /**
     * add a name
     * @param value - The value to add
     */
    async fillName(value: string): Promise<void> {
        await this.page.locator(UpdateNode.NAME_FIELD).fill(value);
    }

    /**
     * add a code
     * @param value - The value to add
     */
    async fillCode(value: string): Promise<void> {
        await this.page.locator(UpdateNode.CODE_FIELD).fill(value);
    }

    /**
     * Fills the CKEditor description field with the given value.
     * @param id - The ID of the CKEditor description field.
     * @param value - The value to input into the CKEditor description field.
     * @returns A promise that resolves when the value is filled.
     */
    async fillDescription(id: string, value: string): Promise<void> {
        await this.tester.fillCKEditorById(`${UpdateNode.DESCRIPTION_FIELD}${id}`, value);
    }

    /**
     * select the update button
     */
    async clickUpdateButton(): Promise<void> {
        await this.page.getByTestId(UpdateNode.DATATEST_UPDATE_BUTTON).click();
    }
}
