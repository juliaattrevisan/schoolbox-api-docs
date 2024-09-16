import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { Page } from "@playwright/test";

export default class PastoralProfile {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/pastoral/student/{studentId}";

    static readonly DELETE_COMMENT_ICON = 'a[href="#delete-comment"]';

    static readonly PASTORAL_POST_COMMENT = "#submit-for-comment-public";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Generates route for this page.
     * @param id - The student id
     * @returns string - The URL
     */
    async generateRoute(id: number): string {
        await this.page.goto(PastoralProfile.URL.replace("{id}", id.toString()));
    }

    /**
     * click Create Pastoral Record button
     */
    async clickCreatePastoralRecord(): Promise<void> {
        await this.page.click("Create Pastoral Record");
    }

    /**
     * click Modify Pastoral Record icon
     * @param studentId - The student id
     * @param recordId - The record id
     */
    async clickModifyIcon(studentId: number, recordId: number): Promise<void> {
        await this.page.click(`href="/pastoral/student/'${studentId}"/record/"${recordId}'/update"`);
    }

    /**
     * click Delete Pastoral Record icon
     * @param recordId - The record id
     */
    async clickDeleteIcon(recordId: number): Promise<void> {
        await this.page.click(`a[id="${recordId}"]`);
    }

    /**
     * click a Pastoral Record
     * @param studentId - The student id
     * @param recordId - The record id
     */
    async clickPastoralRecord(studentId: number, recordId: number): Promise<void> {
        await this.page.click(`href="/pastoral/student/'${studentId}"/record/"${recordId}'"`);
    }

    /**
     * click delete comment icon for learning moments, news, pastoral care records
     */
    async clickDeleteCommentIcon(): Promise<void> {
        await this.page.click(PastoralProfile.DELETE_COMMENT_ICON);
    }

    /**
     * click post comment button
     */
    async clickPostComment(): Promise<void> {
        await this.page.click(PastoralProfile.PASTORAL_POST_COMMENT);
    }
}
