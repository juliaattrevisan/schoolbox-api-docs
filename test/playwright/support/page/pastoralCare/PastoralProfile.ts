import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page } from "@playwright/test";

export default class PastoralProfile {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/pastoral/student/{studentId}";

    static readonly DELETE_COMMENT_ICON = 'a[href="#delete-comment"]';

    static readonly PASTORAL_POST_COMMENT = "#submit-for-comment-public";

    static readonly CREATE_PASTORAL_BUTTON = "a[title='Create Pastoral Record']";

    static readonly PASTORAL_RECORD = "a[href='/pastoral/student/{studentId}/record/{recordId}']";

    static readonly PASTORAL_RECORD_UPDATE = "a[href='/pastoral/student/{studentId}/record/{recordId}/update']";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to the profile page
     * @param id - The user id
     */
    async goto(id: string): Promise<void> {
        await this.page.goto(PastoralProfile.URL.replace("{studentId}", id.toString()));
    }

    /**
     * click Create Pastoral Record button
     */
    async clickCreatePastoralRecord(): Promise<void> {
        await this.page.click(PastoralProfile.CREATE_PASTORAL_BUTTON);
    }

    /**
     * click Modify Pastoral Record icon
     * @param studentId - The student id
     * @param recordId - The record id
     */
    async clickModifyIcon(studentId: string, recordId: number): Promise<void> {
        await this.page.click(
            PastoralProfile.PASTORAL_RECORD_UPDATE.replace("{studentId}", studentId.toString()).replace(
                "{recordId}",
                recordId.toString(),
            ),
        );
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
    async clickPastoralRecord(studentId: string, recordId: number): Promise<void> {
        await this.page.click(
            PastoralProfile.PASTORAL_RECORD.replace("{studentId}", studentId.toString()).replace(
                "{recordId}",
                recordId.toString(),
            ),
        );
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
