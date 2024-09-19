import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { Page } from "@playwright/test";

export default class CreatePastoralRecord {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/pastoral/student/{studentId}/record/insert";

    static readonly PASTORAL_TITLE = "input#heading";

    static readonly PASTORAL_TYPE = "select#typeId";

    static readonly PASTORAL_SUB_TYPE = "select#subtypeId";

    static readonly SAVE_BUTTON = "input.submit";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * type a title for the pastoral record
     * @param pastoralTitle - The title of the pastoral record
     */
    async typeTitle(pastoralTitle: string): Promise<void> {
        await this.page.fill(CreatePastoralRecord.PASTORAL_TITLE, pastoralTitle);
    }

    /**
     * click type dropdown
     */
    async clickTypeDropdown(): Promise<void> {
        await this.page.click(CreatePastoralRecord.PASTORAL_TYPE);
    }

    /**
     * click subType dropdown
     */
    async clickSubTypeDropdown(): Promise<void> {
        await this.page.click(CreatePastoralRecord.PASTORAL_SUB_TYPE);
    }

    /**
     * click one of the types by passing its int value: 1 for Academic, 2 for Attendance, 3 for Medical, 4 for Bullying, 5 for Discipline
     * click one of the subtype by passing its int value: if type Academic then subtypes 1 for Award/Achievement, 2 for Learning Difficulties etc
     * @param type - The type of the pastoral record
     */
    async selectTypeSubType(type: string): Promise<void> {
        // await this.page.click(`option[value="${type}"]`);
        await this.page.locator("#typeId").selectOption({ value: type });
    }

    /**
     * click record visibility links by passing its title: Role, User, Pastoral Group
     * @param visibilityTitle - The title of the visibility link
     */
    async clickVisibilityLink(visibilityTitle: string): Promise<void> {
        await this.page.click(`a[title="Add ${visibilityTitle}"]`);
    }

    /**
     * click a pastoral role in the dialog: Administration, Teacher, Parent
     * @param role  - The pastoral role for the pastoral record
     */
    async clickPastoralRole(role: string): Promise<void> {
        await this.page.click(`li[data-role-name="${role}"]`);
    }

    /**
     * click Save button
     */
    async clickSave(): Promise<void> {
        await this.page.locator(CreatePastoralRecord.SAVE_BUTTON).last().click();
    }
}
