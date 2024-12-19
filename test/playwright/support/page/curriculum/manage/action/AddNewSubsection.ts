import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import ManageCurriculum from "@schoolboxTest/playwright/support/page/curriculum/manage/ManageCurriculum";

export default class AddNewSubsection {
    private page: Page;

    private tester: AcceptanceTester;

    static readonly DISPLAY_TOGGLE = "Add subsection to ";

    static readonly NAME_FIELD = "#name";

    static readonly CODE_FIELD = "#code";

    static readonly ADD_BUTTON = "[data-test-add-subsection]";

    constructor(page: Page, tester: AcceptanceTester) {
        this.page = page;
        this.tester = tester;
    }

    /**
     * select the add new subsection button
     * @param value - The locator of the node
     */
    async clickDisplay(value: string): Promise<void> {
        await this.page.getByRole("button", { name: `${AddNewSubsection.DISPLAY_TOGGLE}${value}` }).click();
    }

    /**
     * check if button is disabled, should be when no name has been entered
     */
    async seeAddButtonIsDisabled(): Promise<void> {
        await expect(this.page.locator(AddNewSubsection.ADD_BUTTON)).toBeDisabled();
    }

    /**
     * add a name
     * @param value - The value to add
     */
    async fillName(value: string): Promise<void> {
        await this.page.locator(AddNewSubsection.NAME_FIELD).fill(value);
    }

    /**
     * add a code
     * @param value - The value to add
     */
    async fillCode(value: string): Promise<void> {
        await this.page.locator(AddNewSubsection.CODE_FIELD).fill(value);
    }

    /**
     * select the add button
     */
    async clickAddButton(): Promise<void> {
        await this.page.locator(AddNewSubsection.ADD_BUTTON).click();
    }

    /**
     * check if a subsection by a given name exists in the list
     * @param name - The name to check
     */
    async seeSubsectionExists(name: string): Promise<void> {
        await expect(this.page.getByTestId(ManageCurriculum.DATATEST_MC_VIEW_NODES_LIST)).toContainText(name);
    }
}
