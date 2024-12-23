import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import ManageCurriculum from "@schoolboxTest/playwright/support/page/curriculum/manage/ManageCurriculum";

export default class AddNewCurriculum {
    private page: Page;

    private tester: AcceptanceTester;

    static readonly DATATEST_DISPLAY_TOGGLE = "toggle-create-curriculum";

    static readonly NAME_FIELD = "#name";

    static readonly CODE_FIELD = "#code";

    static readonly DATATEST_ADD_BUTTON = "add-curriculum";

    constructor(page: Page, tester: AcceptanceTester) {
        this.page = page;
        this.tester = tester;
    }

    /**
     * select the add new curriculum button
     */
    async clickDisplay(): Promise<void> {
        await this.page.getByTestId(AddNewCurriculum.DATATEST_DISPLAY_TOGGLE).click();
    }

    /**
     * check if button is disabled, should be when no name has been entered
     */
    async seeAddButtonIsDisabled(): Promise<void> {
        await expect(this.page.getByTestId(AddNewCurriculum.DATATEST_ADD_BUTTON)).toBeDisabled();
    }

    /**
     * add a name
     * @param value - The value to add
     */
    async fillName(value: string): Promise<void> {
        await this.page.locator(AddNewCurriculum.NAME_FIELD).fill(value);
    }

    /**
     * add a code
     * @param value - The value to add
     */
    async fillCode(value: string): Promise<void> {
        await this.page.locator(AddNewCurriculum.CODE_FIELD).fill(value);
    }

    /**
     * click the add button
     */
    async clickAddButton(): Promise<void> {
        await this.page.getByTestId(AddNewCurriculum.DATATEST_ADD_BUTTON).click();
    }

    /**
     * check if a curriculum by a given name exists in the list
     * @param name - The name to check
     */
    async seeCurriculumExists(name: string): Promise<void> {
        await expect(this.page.getByTestId(ManageCurriculum.DATATEST_MC_VIEW_NODES_LIST)).toContainText(name);
    }
}
