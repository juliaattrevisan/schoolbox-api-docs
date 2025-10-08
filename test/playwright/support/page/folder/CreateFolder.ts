import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type DatabaseHelper from "@schoolboxTest/playwright/support/DatabaseHelper";
import { expect } from "@schoolboxTest/playwright/support/integrations";

export default class CreateFolder {
    private page: Page;

    private tester: AcceptanceTester;

    static readonly URL = "/resource/folder/0/create";

    static readonly URL_RESOURCES = "/resources";

    static readonly DATA_TEST_FOLDER_FORM_INPUT_NAME = "folder-form-input-name";

    static readonly DATA_TEST_FOLDER_FORM_SELECT_TEMPLATE = "folder-form-select-template";

    static readonly DATA_TEST_FOLDER_FORM_RADIO_ACCESS_GROUP = "folder-form-radio-access-group"; // Append "-[type]-[groupID]"

    static readonly DATA_TEST_FOLDER_FORM_SUBMIT = "folder-form-submit";

    static readonly SELECTOR_CREATE_GROUP = "label[for=groupcheck]";

    static readonly SELECTOR_CLASS_CODE = "input[name=code]";

    constructor(tester: AcceptanceTester, page: Page) {
        this.page = page;
        this.tester = tester;
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
        await this.page.getByTestId(CreateFolder.DATA_TEST_FOLDER_FORM_INPUT_NAME).fill(folderName);
    }

    /**
     * Sets a homepage template
     * @param option - The option to set (by label - e.g. "Blog" or "Course")
     */
    async setHomepageTemplate(option: string): Promise<void> {
        await this.page.getByTestId(CreateFolder.DATA_TEST_FOLDER_FORM_SELECT_TEMPLATE).selectOption({ label: option });
    }

    /**
     * Sets access permission eg: 1, "none" ; 2, "read" ; 3, "write"
     * @param groupId - The group ID to set
     * @param permissionLevel - The permission level to set
     */
    async setAccessPermission(groupId: string | number, permissionLevel: "none" | "read" | "write"): Promise<void> {
        await this.page
            .getByTestId(`${CreateFolder.DATA_TEST_FOLDER_FORM_RADIO_ACCESS_GROUP}-${permissionLevel}-${groupId}`)
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
        await this.page.getByTestId(CreateFolder.DATA_TEST_FOLDER_FORM_SUBMIT).click();
    }

    /**
     * Creates a folder
     * @param folderName - The folder name
     * @param homepageTemplateOption - The homepage template option to set
     * @param db - The database helper
     * @param permissionMap - The permission map to set:group id and permission level eg: [1, "write"], [2, "read"]
     * @param schoolCode - The get the school code set send the string
     * @param clickCreateGroupToggle - To click the creeate group toggle send true - by default it is false
     */
    async createFolder(
        folderName: string,
        homepageTemplateOption: string,
        db: DatabaseHelper,
        permissionMap: [number, "none" | "read" | "write"][],
        schoolCode: string,
        clickCreateGroupToggle: boolean = false,
    ): Promise<void> {
        this.tester.isGoingTo("Create a folder");
        await this.goto();
        this.tester.isGoingTo("type a folder name, select a template option and set access permissions");
        await this.typeName(folderName);
        await this.setHomepageTemplate(homepageTemplateOption);

        // for eslint reasons we are not allowed to do await in for loops so we cannot use for - instead we use reduce
        // Iterates over each of the items in the list
        await permissionMap.reduce(async (prev, [key, value]) => {
            await prev; // Wait for the previous iteration to complete
            return this.setAccessPermission(key, value);
        }, Promise.resolve());

        this.tester.isGoingTo("type a school code");
        await this.typeClassCode(schoolCode);

        this.tester.isGoingTo("click the create group toggle or not");
        if (clickCreateGroupToggle) {
            await this.clickCreateGroupToggle();
        }

        this.tester.isGoingTo("click create folder button");
        await this.submit();

        this.tester.isGoingTo("Checking the  folder is created");
        const folderId = (await db.grabFromDatabase("folder", "id", { deleted_at: null, name: folderName })) as string;
        expect(folderId).not.toBe(null);
    }
}
