import { Page } from "@playwright/test";

export default class CreateFolder {
    private page: Page;

    static readonly URL = "/resource/folder/0/create";

    static readonly URL_RESOURCES = "/resources";

    static readonly DATATEST_FOLDER_FORM_INPUT_NAME = "folder-form-input-name";

    static readonly DATATEST_FOLDER_FORM_SELECT_TEMPLATE = "folder-form-select-template";

    static readonly DATATEST_FOLDER_FORM_RADIO_ACCESS_GROUP = "folder-form-radio-access-group"; // Append "-[type]-[groupID]"

    static readonly DATATEST_FOLDER_FORM_SUBMIT = "folder-form-submit";

    static readonly SELECTOR_CREATE_GROUP = "label[for=groupcheck]";

    static readonly SELECTOR_CLASS_CODE = "input[name=code]";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Navigates to create folder page
     */
    async goto(): Promise<void> {
        await this.page.goto(CreateFolder.URL);
    }

    /**
     * Go to resources page
     */
    async gotoResources(): Promise<void> {
        await this.page.goto(CreateFolder.URL_RESOURCES);
    }

    /**
     * Types a folder name
     * @param folderName - The folder name to type
     */
    async typeName(folderName: string): Promise<void> {
        await this.page.getByTestId(CreateFolder.DATATEST_FOLDER_FORM_INPUT_NAME).fill(folderName);
    }

    /**
     * Sets a homepage template
     * @param option - The option to set (by label - e.g. "Blog" or "Course")
     */
    async setHomepageTemplate(option: string): Promise<void> {
        await this.page.getByTestId(CreateFolder.DATATEST_FOLDER_FORM_SELECT_TEMPLATE).selectOption({ label: option });
    }

    /**
     * Sets access permission
     * @param groupId - The group ID to set
     * @param permissionLevel - The permission level to set
     */
    async setAccessPermission(groupId: string | number, permissionLevel: "none" | "read" | "write"): Promise<void> {
        await this.page
            .getByTestId(`${CreateFolder.DATATEST_FOLDER_FORM_RADIO_ACCESS_GROUP}-${permissionLevel}-${groupId}`)
            .check();
    }

    /**
     * Clicks the create group toggle
     */
    async clickCreateGroupToggle(): Promise<void> {
        // TODO: Use data-test
        await this.page.click(CreateFolder.SELECTOR_CREATE_GROUP);
    }

    /**
     * Types a class code
     * @param code - The code to type
     */
    async typeClassCode(code: string): Promise<void> {
        // TODO: Use data-test
        await this.page.fill(CreateFolder.SELECTOR_CLASS_CODE, code);
    }

    /**
     * Clicks the create folder button
     */
    async submit(): Promise<void> {
        await this.page.getByTestId(CreateFolder.DATATEST_FOLDER_FORM_SUBMIT).click();
    }
}
