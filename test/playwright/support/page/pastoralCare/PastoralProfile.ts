import type { Page, Locator } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { expect } from "@playwright/test";

export default class PastoralProfile {
    private page: Page;

    private tester: AcceptanceTester;

    static readonly URL = "/pastoral/student/{studentId}";

    static readonly DELETE_COMMENT_ICON = 'a[href="#delete-comment"]';

    static readonly PASTORAL_POST_COMMENT = "#submit-for-comment-public";

    static readonly CREATE_PASTORAL_BUTTON = "a[title='Create Pastoral Record']";

    static readonly PASTORAL_RECORD = "a[href='/pastoral/student/{studentId}/record/{recordId}']";

    static readonly PASTORAL_RECORD_UPDATE = "a[href='/pastoral/student/{studentId}/record/{recordId}/update']";

    static readonly DATA_TEST_PASTORAL_RECORDS = "student-section-records-pastoral-record";

    static readonly DATA_TEST_SHOW_ARCHIVED_RECORDS_TOGGLE = "student-section-filter-show-archived-records-toggle";

    static readonly SELECTOR_PROFILE_CONTENT = "div.profile.content";

    static readonly SELECTOR_PANEL_GENERAL = "div#panel-general";

    static readonly SELECTOR_PANEL_CONFIDENTIAL = "div#panel-confidential";

    static readonly SELECTOR_PANEL_CONFIDENTIAL_LINK = "a#panel-confidential";

    static readonly SELECTOR_DELETE_PASTORAL_RECORD_ICON = 'a[href="/pastoral/record/{studentId}/delete"]';

    constructor(page: Page, tester: AcceptanceTester) {
        this.page = page;
        this.tester = tester;
    }

    /**
     * Navigates to the profile page
     * @param id - The user id
     */
    async goto(id: number): Promise<void> {
        await this.page.goto(PastoralProfile.URL.replace("{studentId}", id.toString()));
    }

    /**
     * click Create Pastoral Record button
     */
    async clickCreatePastoralRecord(): Promise<void> {
        await this.page.locator(PastoralProfile.CREATE_PASTORAL_BUTTON).click();
    }

    /**
     * click Modify Pastoral Record icon
     * @param studentId - The student id
     * @param recordId - The record id
     */
    async clickModifyIcon(studentId: number, recordId: number): Promise<void> {
        await this.page
            .locator(
                PastoralProfile.PASTORAL_RECORD_UPDATE.replace("{studentId}", studentId.toString()).replace(
                    "{recordId}",
                    recordId.toString(),
                ),
            )
            .click();
    }

    /**
     * click Delete Pastoral Record icon
     * @param recordId - The record id
     */
    async clickDeleteIcon(recordId: number): Promise<void> {
        await this.page.locator(`a[id="${recordId}"]`).click();
    }

    /**
     * click a Pastoral Record
     * @param studentId - The student id
     * @param recordId - The record id
     */
    async clickPastoralRecord(studentId: number, recordId: number): Promise<void> {
        await this.page
            .locator(
                PastoralProfile.PASTORAL_RECORD.replace("{studentId}", studentId.toString()).replace(
                    "{recordId}",
                    recordId.toString(),
                ),
            )
            .click();
    }

    /**
     * click delete comment icon for learning moments, news, pastoral care records
     */
    async clickDeleteCommentIcon(): Promise<void> {
        await this.page.locator(PastoralProfile.DELETE_COMMENT_ICON).click();
    }

    /**
     * click post comment button
     */
    async clickPostComment(): Promise<void> {
        await this.page.locator(PastoralProfile.PASTORAL_POST_COMMENT).click();
    }

    /**
     * Get the current records
     * @returns - The current records
     */
    async getRecords(): Promise<Locator[]> {
        return this.page.getByTestId(PastoralProfile.DATA_TEST_PASTORAL_RECORDS).all();
    }

    /**
     * click post comment button
     */
    async clickShowArchivedRecordsToggle(): Promise<void> {
        await this.page.getByTestId(PastoralProfile.DATA_TEST_SHOW_ARCHIVED_RECORDS_TOGGLE).click();
    }

    /**
     * check the profile content contains the text
     * @param text - The text to check
     */
    async seeInProfileContent(text: string): Promise<void> {
        await expect(this.page.locator(PastoralProfile.SELECTOR_PROFILE_CONTENT)).toContainText(text);
    }

    /**
     * check the general panel contains the text
     * @param text - The text to check
     */
    async seeInGeneralPanel(text: string): Promise<void> {
        await expect(this.page.locator(PastoralProfile.SELECTOR_PANEL_GENERAL)).toContainText(text);
    }

    /**
     * click confidential panel link
     */
    async clickConfidentialPanelLink(): Promise<void> {
        await this.page.locator(PastoralProfile.SELECTOR_PANEL_CONFIDENTIAL_LINK).click();
    }

    /**
     * check the confidential panel contains the text
     * @param text - The text to check
     */
    async seeInConfidentialPanel(text: string): Promise<void> {
        await expect(this.page.locator(PastoralProfile.SELECTOR_PANEL_CONFIDENTIAL)).toContainText(text);
    }

    /**
     * delete the pastoral record delete by passing its record id
     * @param recordId - The student id
     */
    async deletePastoralRecord(recordId: number): Promise<void> {
        const deleteSelector = PastoralProfile.SELECTOR_DELETE_PASTORAL_RECORD_ICON.replace(
            "{studentId}",
            recordId.toString(),
        );
        await this.page.locator(deleteSelector).click();
        await this.tester.common.clickConfirmButton();
    }
}
