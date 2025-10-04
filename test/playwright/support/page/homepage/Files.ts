import { expect } from "@playwright/test";
import type { Locator, Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
export default class Files {
    private tester: AcceptanceTester;
    private page: Page;

    static readonly ELEMENT_FILE_TITLE_TEXTBOX = "#description";
    static readonly ELEMENT_FILE_DESCRIPTION_TEXTBOX = "#comment";
    static readonly FILE_TILE_TITLE_TRANSLUCENT = 'div.translucent h3:has-text("{fileTitle}")';
    static readonly FILE_TILE_TITLE_NONTRANSLUCENT = 'div h3:has-text("{fileTitle}")';
    static readonly ELEMENT_POST_FILE_BUTTON = 'input[value="Post File"]';
    static readonly ELEMENT_ATTACH_FILE_BUTTON = "#CmsZone";
    static readonly ELEMENT_MY_FILES_LINK = 'a.icon-files[href="#filesystem-myfile-panel"]';
    static readonly ELEMENT_FILE_ITEM = 'a[title="Abby"]';
    static readonly ELEMENT_PREVIEW_FILES = '.action-list li a[data-plugin="preview"] h3';
    static readonly ELEMENT_FILE_VISIBILITY_CHECKBOX_INPUT = "#visible";
    static readonly ELEMENT_FILE_VISIBILITY_CHECKBOX_LABEL = 'label[for="visible"]';

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Check if a specific file exists in a files component.
     * @param component - Root locator for the Files component to search within.
     * @param name - The user-visible file name (tile/link text).
     */
    public async checkFileExists(component: Locator, name: string): Promise<void> {
        this.tester.isGoingTo(`Check the file called ${name}`);
        await expect(component.locator("h3 a", { hasText: name })).toBeVisible();
    }

    /*
     * Verify if a file is present in a files component.
     * @param container - Root locator for the Files component to search within.
     * @param fileTitle - The file title to verify.
     * @param translucent - Whether the component is rendered in translucent mode.
     */
    public static async verifyFileTitle(
        container: Locator,
        fileTitle: string,
        translucent: boolean = false,
    ): Promise<void> {
        if (translucent) {
            await expect(
                container.locator(Files.FILE_TILE_TITLE_TRANSLUCENT.replace("{fileTitle}", fileTitle)),
            ).toBeVisible();
        } else {
            await expect(
                container.locator(Files.FILE_TILE_TITLE_NONTRANSLUCENT.replace("{fileTitle}", fileTitle)),
            ).toBeVisible();
        }
    }

    /**
     * Add/post a file into the Files component.
     * @param component - Root locator for this Files component.
     * @param fileTitle - Title to enter in the form.
     * @param description - Description to enter in the form.
     * @param expectedVisibility - Whether the file should be visible after posting.
     */
    public static async selectFile(
        page: Page,
        component: Locator,
        fileTitle: string,
        description: string,
        expectedVisibility: boolean,
    ): Promise<void> {
        await component.getByTestId("file-select-link").click();
        await page.fill(Files.ELEMENT_FILE_TITLE_TEXTBOX, fileTitle);
        await page.fill(Files.ELEMENT_FILE_DESCRIPTION_TEXTBOX, description);
        await page.click(Files.ELEMENT_ATTACH_FILE_BUTTON);
        await page.click(Files.ELEMENT_MY_FILES_LINK);
        await page.click(Files.ELEMENT_FILE_ITEM);

        // Ensure visibility matches expectation:
        // - read state from INPUT
        // - toggle via LABEL (custom UIs often hide the input)
        const actualVisibility = await page.isChecked(Files.ELEMENT_FILE_VISIBILITY_CHECKBOX_INPUT);
        if (expectedVisibility && !actualVisibility) {
            await page.click(Files.ELEMENT_FILE_VISIBILITY_CHECKBOX_LABEL);
        } else if (!expectedVisibility && actualVisibility) {
            await page.click(Files.ELEMENT_FILE_VISIBILITY_CHECKBOX_LABEL);
        }

        await page.click(Files.ELEMENT_POST_FILE_BUTTON);
    }

    /**
     * Filter to images only, then save.
     * @param filesComponent - Root locator for the Files component.
     */
    public static async sortImageFiles(filesComponent: Locator): Promise<void> {
        await filesComponent.locator('label[for$="-filter-document"]').click();
        await filesComponent.locator('label[for$="-filter-audio"]').click();
        await filesComponent.locator('label[for$="-filter-video"]').click();
        await filesComponent.locator('label[for$="-filter-other"]').click();
        await filesComponent.locator('input[value="Update"]').click();
        await expect(filesComponent.locator('select[name="sort"]')).toBeHidden();
    }

    /**
     * Filter to video + document, then save.
     * @param filesComponent - Root locator for the Files component.
     */
    public static async sortVideoAndDocumentFiles(filesComponent: Locator): Promise<void> {
        await filesComponent.locator('label[for$="-filter-document"]').click();
        await filesComponent.locator('label[for$="-filter-video"]').click();
        await filesComponent.locator('label[for$="-filter-image"]').click();
        await filesComponent.locator('input[value="Update"]').click();
        await expect(filesComponent.locator('select[name="sort"]')).toBeHidden();
    }

    /*
     * Retrieve all file titles from a files component.
     * @param filesComponent - Root locator for the Files component.
     * @returns Array of file titles.
     */
    public static async getAllFiles(filesComponent: Locator): Promise<string[]> {
        const filesLocator = filesComponent.locator(Files.ELEMENT_PREVIEW_FILES);
        const fileTexts = await filesLocator.allTextContents();
        return fileTexts;
    }

    /**
     * Apply sorting + update.
     * @param filesComponent - Root locator for the Files component.
     * @param sortBy - e.g. 'name' | 'date'
     * @param sortingOrder - e.g. 'asc' | 'desc'
     */
    public static async sortFiles(filesComponent: Locator, sortBy: string, sortingOrder: string): Promise<void> {
        await filesComponent.locator('label[for$="-filter-audio"]').click();
        await filesComponent.locator('label[for$="-filter-image"]').click();
        await filesComponent.locator('label[for$="-filter-other"]').click();
        await filesComponent.locator('select[name="sort"]').selectOption(sortBy);
        await filesComponent.locator('select[name="sortdir"]').selectOption(sortingOrder);
        await filesComponent.locator('input[value="Update"]').click();
        await expect(filesComponent.locator('select[name="sort"]')).toBeHidden();
    }

    /**
     * Only change sort fields (no filter toggles) + update.
     * @param filesComponent - Root locator for the Files component.
     * @param sortBy - e.g. 'name' | 'date'
     * @param sortingOrder - e.g. 'asc' | 'desc'
     */
    public static async sortFilesDesc(filesComponent: Locator, sortBy: string, sortingOrder: string): Promise<void> {
        await filesComponent.locator('select[name="sort"]').selectOption(sortBy);
        await filesComponent.locator('select[name="sortdir"]').selectOption(sortingOrder);
        await filesComponent.locator('input[value="Update"]').click();
        await expect(filesComponent.locator('select[name="sort"]')).toBeHidden();
    }

    /**
     * Set the "limit" (page size) and save.
     * @param fileComponent - Root locator for the Files component.
     * @param limit - '1' | '2' | 'All'
     */
    public static async setFileLimit(fileComponent: Locator, limit: string): Promise<void> {
        await fileComponent.locator("select[name='size']").selectOption(limit);
        await fileComponent.locator('input[value="Update"]').click();
        await expect(fileComponent.locator('select[name="sort"]')).toBeHidden();
    }

    /**
     * @param fileComponent - Root locator for this Files component; actions are scoped to it.
     *   - Tick compact mode
     *   - Tick show component title
     *   - Clicks Update and waits for the form to close
     */
    public static async updateFileComponentSetting(fileComponent: Locator): Promise<void> {
        await fileComponent.locator('label[for$="-compactMode"]').click();
        await fileComponent.locator('label[for$="-showComponentTitle"]').click();
        await fileComponent.locator('input[value="Update"]').click();
        await expect(fileComponent.locator("select[name='size']")).toBeHidden();
    }

    /**
     * Check the file tile metadata block under a given title.
     * Expects author ("Schoolbox Admin"), timestamp, and optional description.
     * @param fileComponent - Root locator for the Files component.
     * @param expectedTitle - The file tile's <h3> text.
     * @param expectedDesc - Description paragraph to verify (optional).
     */
    public static async checkFileDescription(
        fileComponent: Locator,
        expectedTitle: string,
        expectedDesc: string,
    ): Promise<void> {
        const REGEX_TIMESTAMP =
            /\b(?:(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s\d{1,2},\s\d{4}\s\d{1,2}:\d{2}\s?(?:am|pm)|(?:just now|\d+\s+(?:second|minute|hour|day|week|month|year)s?\s+ago))\b/i;

        const P = fileComponent.locator(
            `//h3[normalize-space()='${expectedTitle}']/ancestor::div[contains(@class,'card')]//p`,
        );

        await expect(P.first()).toBeVisible();
        await expect(P.first()).toContainText("Schoolbox Admin");
        await expect(P.first()).toContainText(REGEX_TIMESTAMP);

        if (expectedDesc) {
            await expect(P.nth(1), "Description <p> missing").toBeVisible();
            await expect(P.nth(1)).toContainText(expectedDesc);
        }
    }

    /*
     * Modify a previously added file.
     * @param fileComponent - Root locator for the Files component.
     * @param fileId - DB id of the file to modify.
     * @param newFileTitle - New value for the title field.
     */
    public async modifyFile(fileComponent: Locator, fileId: number, newFileTitle: string): Promise<void> {
        await fileComponent.locator(`a.icon-modify[title="Modify File"][href^="/cms/file/${fileId}"]`).click();
        await this.page.locator("#description").fill(newFileTitle);
        await this.page.locator('input[value="Modify File"]').click();
        await expect(this.page.locator("#description")).toBeHidden();
    }

    /**
     * Configure the Files component to use an existing folder ("Use This Folder").
     * @param fileComponent - Root locator for the Files component.
     * @param srcFolder - Top-level folder name to open.
     * @param subSrcFolder - Subfolder to select and use.
     */
    public async addExistingFile(fileComponent: Locator, srcFolder: string, subSrcFolder: string): Promise<void> {
        const regexSrc = new RegExp(`^${srcFolder}$`);
        await fileComponent.locator('[id*="modalLinkButton"]', { hasText: "Browse" }).click();
        await this.page.locator("#filesystem-browse-panel a", { hasText: regexSrc }).click();
        const locatorEng9A = this.page.locator("#filesystem-browse-panel li.resource a", { hasText: subSrcFolder });
        await locatorEng9A.click({ delay: 1000 });
        await expect(locatorEng9A).toBeHidden();
        await this.page
            .locator("#filesystem-browse-panel div.component-action a", { hasText: "Use This Folder" })
            .click();
        const locatorEng = fileComponent.locator("div.source a", { hasText: subSrcFolder });
        await expect(locatorEng).toBeVisible();
        await fileComponent.locator('input[type="submit"][value="Update"]').click();
        await expect(locatorEng).toBeHidden();
    }

    /**
     * Update the Files component heading and verify the breadcrumb on the resources page.
     * @param page - Playwright Page.
     * @param filesComponent - Root locator for this Files component.
     * @param classPageId - The homepage/class ID (e.g., 83).
     * @param newHeading - New heading to set (default: "Automation Heading Test").
     */
    public async setHeadingAndVerifyBreadcrumb(
        page: Page,
        filesComponent: Locator,
        classPageId: number,
        newHeading: string = "Automation Heading Test",
    ): Promise<void> {
        await filesComponent.locator('input[name="componentname"]').fill(newHeading);
        await filesComponent.locator('input[value="Update"]').click();
        await filesComponent.getByTestId("view-all-files-button").click();

        // e.g. /resources/folder/4905/files/83
        await expect(page).toHaveURL(new RegExp(`/resources/folder/\\d+/files/${classPageId}$`));
        await expect(page.locator("ul.breadcrumb > li.active > span")).toContainText(newHeading);
    }

    /**
     * Reset the Files component heading (empty -> defaults to "Files") and verify breadcrumb.
     * @param page - Playwright Page.
     * @param filesComponent - Root locator for this Files component.
     * @param classPageId - The homepage/class ID (e.g., 83).
     * @param expectedHeading - Expected default heading after reset (default: "Files").
     */
    public static async resetHeadingAndVerifyBreadcrumb(
        page: Page,
        filesComponent: Locator,
        classPageId: number,
        expectedHeading: string = "Files",
    ): Promise<void> {
        await filesComponent.locator('input[name="componentname"]').fill("");
        await filesComponent.locator('input[value="Update"]').click();
        await filesComponent.getByTestId("view-all-files-button").click();
        await expect(page).toHaveURL(new RegExp(`/resources/folder/\\d+/files/${classPageId}$`));
        await expect(page.locator("ul.breadcrumb > li.active > span")).toContainText(expectedHeading);
    }
}
