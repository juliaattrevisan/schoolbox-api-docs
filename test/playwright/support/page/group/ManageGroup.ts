import { Page } from "@playwright/test";

export default class ManageGroup {
    private page: Page;

    static readonly URL = "/group/modify/{group_id}";

    static readonly URL_GROUP_ID = "{group_id}";

    static readonly SUBMIT_BUTTON = "input[type=submit][value=Submit]";

    static readonly UPDATE_BUTTON = "button.button.submit";

    static readonly ELEMENT_SEARCH = "#search-text";

    static readonly DATATEST_MANAGE_GROUP_ADD_MEMBERS = "manage-group-add-members-submit";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Gets url to create a group
     * @param groupId - The group ID
     * @returns - the URL
     */
    public static getGroupModifyPage(groupId: number): string {
        return this.URL.replace("{group_id}", groupId.toString());
    }

    /**
     * Set access permission
     * free: type-3
     * moderated: type-2
     * restricted: type-1
     * @param accessPermission - The access permission to set
     */
    async setAccessPermission(accessPermission: string): Promise<void> {
        await this.page.locator(`label[for=${accessPermission}][class=radio-label]`).click();
    }

    /**
     * Click submit button
     */
    async clickSubmitButton(): Promise<void> {
        await this.page.locator(ManageGroup.SUBMIT_BUTTON).click();
    }

    /**
     * Click update button
     */
    async clickUpdateButton(): Promise<void> {
        await this.page.locator(ManageGroup.UPDATE_BUTTON).getByText("Update").click();
    }

    /**
     * Click an option on the radio button From: name, group, role, list
     * @param option - The option to click
     */
    async clickFromRadioButton(option: string): Promise<void> {
        await this.page.locator(`label[for=${option}]`).click();
    }

    /**
     * Search user, group, role by typing a text in the search bar
     * @param text - The text to type
     */
    async search(text: string): Promise<void> {
        await this.page.locator(ManageGroup.ELEMENT_SEARCH).click();
        await this.page.locator(ManageGroup.ELEMENT_SEARCH).fill(text);
    }

    /**
     * Select the user in the dropdown by passing user id
     * @param userId - The user ID
     */
    async selectUser(userId: number): Promise<void> {
        await this.page.locator(`label[for=checkbox-${userId}]`).click();
    }

    /**
     * Clicks the Add Members button
     */
    async clickAddMembers(): Promise<void> {
        await this.page.getByTestId(ManageGroup.DATATEST_MANAGE_GROUP_ADD_MEMBERS).click();
    }
}
