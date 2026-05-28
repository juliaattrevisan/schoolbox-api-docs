import { expect } from "@playwright/test";
import type { Locator, Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class Folders {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly DATA_TEST_ADD_FOLDER_BUTTON = "folder-component-add-folder-button";

    static readonly DATA_TEST_FOLDER_TITLE = "folder-component-title";

    static readonly DATA_TEST_FOLDER_FORM_INPUT_NAME = "folder-form-input-name";

    static readonly DATA_TEST_FOLDER_FORM_SUBMIT = "folder-form-submit";

    static readonly DATA_TEST_DISPLAY_VIEW_SELECT = "component-config-displayPortraits-select";

    static readonly DATA_TEST_UPDATE_BUTTON = "homepage-component-update-button";

    static readonly DATA_TEST_LIMIT_SELECT = "component-config-size-select";

    static readonly SELECTOR_CARD_IMAGE = `div[class="card-class-image"]`;

    static readonly DATA_TEST_SORT_SELECT = "component-config-sort-select";

    static readonly DATA_TEST_SORT_DIRECTION_SELECT = "component-config-sortdir-select";

    static readonly DATA_TEST_SHOW_TITLE_CHECKBOX = "component-config-show-title-checkbox";

    static readonly DATA_TEST_TITLEBAR_NAME = "homepage-component-titlebar-name";

    static readonly DATA_TEST_FOLDER_MODIFY_PREFIX = "folder-component-modify-";

    static readonly DATA_TEST_SOURCE_BROWSE_BUTTON = "component-source-browse-button";

    static readonly DATA_TEST_2019_CLASSES = "breadcrumb-name-2019 Classes";

    static readonly DATA_TEST_JUNIOR_SCHOOL = "node-name-Junior School";

    static readonly DATA_TEST_YEAR_3 = "node-name-Year 3";

    static readonly DATA_TEST_3RW_FOLDER = "node-name-3RW (3RW)";

    static readonly DATA_TEST_ARTWORK_FOLDER = "node-name-Artwork ";

    static readonly DATA_TEST_CLASS_PHOTOS_FOLDER = "node-name-Class Photos";

    static readonly DATA_TEST_AUTOMATION_UPDATED_FOLDER = "node-name-Automation Updated Test Folder";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Verify a folder exists in the Folder component.
     * @param component - The Folder component locator.
     * @param folderName - The folder name to verify.
     */
    async verifyFolderExists(component: Locator, folderName: string): Promise<void> {
        this.tester.isGoingTo(`Check the folder called ${folderName}`);

        await expect(
            component.getByTestId(Folders.DATA_TEST_FOLDER_TITLE).filter({ hasText: folderName }),
        ).toBeVisible();
    }

    /**
     * Click the Add Folder button in the Folder component.
     * @param component - The Folder component locator.
     */
    async clickAddFolder(component: Locator): Promise<void> {
        this.tester.isGoingTo("Click Add Folder button");

        await component.getByTestId(Folders.DATA_TEST_ADD_FOLDER_BUTTON).click();
    }

    /**
     * Fill the folder name field on the folder form.
     * @param folderName - The folder name to enter.
     */
    async fillFolderName(folderName: string): Promise<void> {
        this.tester.isGoingTo(`Enter folder name: ${folderName}`);

        await this.page.getByTestId(Folders.DATA_TEST_FOLDER_FORM_INPUT_NAME).fill(folderName);
    }

    /**
     * Submit the folder form.
     */
    async submitCreateFolder(): Promise<void> {
        this.tester.isGoingTo("Click Create Folder button");

        await this.page.getByTestId(Folders.DATA_TEST_FOLDER_FORM_SUBMIT).click();
    }

    /**
     * Save Folder component settings.
     * @param component - The Folder component locator.
     */
    async saveComponentSettings(component: Locator): Promise<void> {
        this.tester.isGoingTo("Save Folder component settings");

        await component.getByTestId(Folders.DATA_TEST_UPDATE_BUTTON).click();
        await expect(component.getByTestId(Folders.DATA_TEST_UPDATE_BUTTON)).toBeHidden();
    }

    /**
     * Set Folder component limit and click Update.
     * @param component - The Folder component locator.
     * @param limit - Limit value, for example "1", "2", or "0" for All.
     */
    public static async setFolderLimit(component: Locator, limit: string): Promise<void> {
        await component.getByTestId(Folders.DATA_TEST_LIMIT_SELECT).selectOption(limit);
        await component.getByTestId(Folders.DATA_TEST_UPDATE_BUTTON).click();
        await expect(component.getByTestId(Folders.DATA_TEST_UPDATE_BUTTON)).toBeHidden();
    }

    /**
     * Configure Folder component view, limit, sort, and direction, then click Update.
     * @param folderComponent - The Folder component locator.
     * @param view - The view to display, for example "list" or "grid".
     * @param limit - The number of folders to display, for example "0" for All, "1", or "2".
     * @param sortBy - The criteria to sort by, for example "Name", "Sequence", or "Created".
     * @param sortingOrder - The sort direction, for example "asc" or "desc".
     */
    public static async configureFolderSettings(
        folderComponent: Locator,
        view: "list" | "grid",
        limit: string,
        sortBy: string,
        sortingOrder: "asc" | "desc",
    ): Promise<void> {
        await folderComponent.getByTestId(Folders.DATA_TEST_DISPLAY_VIEW_SELECT).selectOption(view);
        await folderComponent.getByTestId(Folders.DATA_TEST_LIMIT_SELECT).selectOption(limit);
        await folderComponent.getByTestId(Folders.DATA_TEST_SORT_SELECT).selectOption(sortBy);
        await folderComponent.getByTestId(Folders.DATA_TEST_SORT_DIRECTION_SELECT).selectOption(sortingOrder);
        await folderComponent.getByTestId(Folders.DATA_TEST_UPDATE_BUTTON).click();
        await expect(folderComponent.getByTestId(Folders.DATA_TEST_DISPLAY_VIEW_SELECT)).toBeHidden();
    }

    /**
     * Get all visible folder titles from the Folder component.
     * @param component - The Folder component locator.
     * @returns The visible folder titles.
     */
    public static async getAllFolders(component: Locator): Promise<string[]> {
        const folderTexts = await component.getByTestId(Folders.DATA_TEST_FOLDER_TITLE).allTextContents();

        return folderTexts.map((text) => text.trim());
    }

    /**
     * Get count of visible folder card images in grid view.
     * @param component - The Folder component locator.
     * @returns The number of visible folder card images.
     */
    public static async getAllCardImages(component: Locator): Promise<number> {
        return component.locator(Folders.SELECTOR_CARD_IMAGE).count();
    }

    /**
     * Click Show Title to Read-Only Users checkbox.
     * @param component - The Folder component locator.
     */
    public async clickShowComponentTitle(component: Locator): Promise<void> {
        this.tester.isGoingTo("Click Show Title to Read-Only Users");

        await component.getByTestId(Folders.DATA_TEST_SHOW_TITLE_CHECKBOX).click();
    }

    /**
     * Verify Folder component title is shown.
     * @param component - The Folder component locator.
     * @param expectedTitle - Expected component title.
     */
    public static async verifyComponentTitle(component: Locator, expectedTitle: string): Promise<void> {
        await expect(component.getByTestId(Folders.DATA_TEST_TITLEBAR_NAME)).toBeVisible();
        await expect(component.getByTestId(Folders.DATA_TEST_TITLEBAR_NAME)).toContainText(expectedTitle);
    }

    /**
     * Verify Folder component title is not visible.
     * @param component - The Folder component locator.
     */
    public static async verifyComponentTitleNotVisible(component: Locator): Promise<void> {
        await expect(component.getByTestId(Folders.DATA_TEST_TITLEBAR_NAME)).toBeHidden();
    }

    /**
     * Click modify icon for an existing folder, update the folder title, and save.
     * @param component - The Folder component locator.
     * @param currentFolderTitle - Existing folder title to verify before editing.
     * @param newFolderTitle - New folder title to save.
     */
    public async updateExistingFolderTitle(
        component: Locator,
        currentFolderTitle: string,
        newFolderTitle: string,
    ): Promise<void> {
        this.tester.isGoingTo(`Check existing folder title: ${currentFolderTitle}`);
        await this.verifyFolderExists(component, currentFolderTitle);

        this.tester.isGoingTo(`Click modify icon for folder ${currentFolderTitle}`);
        await component.getByTestId(`${Folders.DATA_TEST_FOLDER_MODIFY_PREFIX}${currentFolderTitle}`).click();

        this.tester.isGoingTo(`Update folder title to ${newFolderTitle}`);
        await this.page.getByTestId(Folders.DATA_TEST_FOLDER_FORM_INPUT_NAME).fill(newFolderTitle);

        this.tester.isGoingTo("Click Modify Folder button");
        await this.page.getByTestId(Folders.DATA_TEST_FOLDER_FORM_SUBMIT).click();
    }

    /**
     * Select alt source folder and update the Folder component.
     * @param folderComponent - The Folder component locator.
     */
    public async selectAltSourceFolders(folderComponent: Locator): Promise<void> {
        this.tester.isGoingTo("Browse alt source folder");

        const browsePanel = this.page.locator("#filesystem-browse-panel");

        await folderComponent.getByTestId(Folders.DATA_TEST_SOURCE_BROWSE_BUTTON).click();
        await expect(browsePanel).toBeVisible();
        this.tester.isGoingTo("Open 2019 Classes");
        await browsePanel.getByTestId(Folders.DATA_TEST_2019_CLASSES).click();
        this.tester.isGoingTo("Open Junior School");
        await browsePanel.getByTestId(Folders.DATA_TEST_JUNIOR_SCHOOL).click();

        this.tester.isGoingTo("Open Year 3");
        await browsePanel.getByTestId(Folders.DATA_TEST_YEAR_3).click();

        this.tester.isGoingTo("Select 3RW (3RW) folder");
        await browsePanel.getByTestId(Folders.DATA_TEST_3RW_FOLDER).click();

        this.tester.isGoingTo("Verify 3RW child folders are shown");
        await expect(browsePanel.getByTestId(Folders.DATA_TEST_ARTWORK_FOLDER)).toBeVisible();
        await expect(browsePanel.getByTestId(Folders.DATA_TEST_CLASS_PHOTOS_FOLDER)).toBeVisible();
        await expect(browsePanel.getByTestId(Folders.DATA_TEST_AUTOMATION_UPDATED_FOLDER)).toBeVisible();

        this.tester.isGoingTo("Click Use This Folder");
        await this.page.locator("a[data-use-this-folder-component-action]").click();

        this.tester.isGoingTo("Update Folder component source");
        await folderComponent.getByTestId(Folders.DATA_TEST_UPDATE_BUTTON).click();

        await expect(folderComponent.getByTestId(Folders.DATA_TEST_UPDATE_BUTTON)).toBeHidden();
    }
}
