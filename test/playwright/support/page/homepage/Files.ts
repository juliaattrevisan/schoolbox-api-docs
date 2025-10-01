import { expect } from "@playwright/test";
import type { Locator, Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class Files {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly ELEMENT_TITLE_TEXTBOX = 'input[id="description"]';
    static readonly FILE_TILE_TITLE_TRANSLUCENT = 'div.translucent h3:has-text("{fileTitle}")';
    static readonly FILE_TILE_TITLE_NONTRANSLUCENT = 'div h3:has-text("{fileTitle}")';
    static readonly ELEMENT_FILE_TITLE_TEXTBOX = 'input[id="description"]';
    static readonly ELEMENT_FILE_DESCRIPTION_TEXTBOX = 'textarea[id="comment"]';
    static readonly ELEMENT_POST_FILE_BUTTON = 'input[value="Post File"]';
    static readonly ELEMENT_FILE_VISIBILITY_CHECKBOX = 'label[for="visible"]';
    static readonly ELEMENT_ATTACH_FILE_BUTTON = "#CmsZone";
    static readonly ELEMENT_MY_FILES_LINK = 'a.icon-files[href="#filesystem-myfile-panel"]';
    static readonly ELEMENT_FILE_ITEM = 'a[title="Abby"]';
    static readonly ELEMENT_PREVIEW_FILES = '.action-list li a[data-plugin="preview"] h3';

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Check if a specific link exists in a links component
     * @param component - The links component
     * @param name - The name of the link shown to the user
     */
    public async checkFileExists(component: Locator, name: string): Promise<void> {
        this.tester.isGoingTo(`Check the file called ${name}`);
        await expect(component.locator("h3 a", { hasText: name })).toBeVisible();
    }

    /*
     * Verify if a file is present in a files component
     * @param container - The files component locator
     * @param fileTitle - The title of the file
     * @param translucent - Whether the component is in translucent mode (default: false)
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
     * Clicking add link button in a link component
     * @param component - The links component
     * @param fileTitle - The title of the link
     * @param description - The description of the link
     *
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
        await page.click(Files.ELEMENT_MY_FILES_LINK);
        await page.click(Files.ELEMENT_FILE_ITEM);

        await page.click(Files.ELEMENT_FILE_VISIBILITY_CHECKBOX);
        const actualVisibility = await page.isChecked(Files.ELEMENT_FILE_VISIBILITY_CHECKBOX);

        if (expectedVisibility && !actualVisibility) {
            await page.click(Files.ELEMENT_FILE_VISIBILITY_CHECKBOX);
        } else if (!expectedVisibility && actualVisibility) {
            await page.click(Files.ELEMENT_FILE_VISIBILITY_CHECKBOX);
        }

        await page.click(Files.ELEMENT_POST_FILE_BUTTON);
    }

    /**
     *
     * @param filesComponent - The files component locator

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
     *
     * @param filesComponent - The files component locator

     */
    public static async sortVideoAndDocumentFiles(filesComponent: Locator): Promise<void> {
        await filesComponent.locator('label[for$="-filter-document"]').click();
        await filesComponent.locator('label[for$="-filter-video"]').click();
        await filesComponent.locator('label[for$="-filter-image"]').click();
        await filesComponent.locator('input[value="Update"]').click();
        await expect(filesComponent.locator('select[name="sort"]')).toBeHidden();
    }

    /*
     * Retrieve all file titles from a files component
     * @param filesComponent - The files component locator
     * @returns An array of file titles
     */
    public static async getAllFiles(filesComponent: Locator): Promise<string[]> {
        const filesLocator = filesComponent.locator(Files.ELEMENT_PREVIEW_FILES);
        const fileTexts = await filesLocator.allTextContents();
        return fileTexts;
    }

    /**
     *
     * @param filesComponent - The files component locator
     * @param sortBy - The criteria to sort by (e.g., 'Name' or 'Date')
     * @param sortingOrder - The order of sorting (e.g., 'Ascending' or 'Descending')
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
     *
     * @param filesComponent - The files component locator
     * @param sortBy - The criteria to sort by (e.g., 'Name' or 'Date')
     * @param sortingOrder - The order of sorting (e.g., 'Ascending' or 'Descending')
     */
    public static async sortFilesDesc(filesComponent: Locator, sortBy: string, sortingOrder: string): Promise<void> {
        await filesComponent.locator('select[name="sort"]').selectOption(sortBy);
        await filesComponent.locator('select[name="sortdir"]').selectOption(sortingOrder);
        await filesComponent.locator('input[value="Update"]').click();
        await expect(filesComponent.locator('select[name="sort"]')).toBeHidden();
    }

    /**
     *
     * @param linkComponent - The links component locator
     * @param Limit - The number of links to display (e.g., '5', '10', '15', '25', 'All')
     */
    public static async setFileLimit(fileComponent: Locator, Limit: string): Promise<void> {
        await fileComponent.locator("select[name='size']").selectOption(Limit);
        await fileComponent.locator('input[value="Update"]').click();
        await expect(fileComponent.locator('select[name="sort"]')).toBeHidden();
    }

    /**
     *
     * @param fileComponent - The files component locator
     * @param limit - The number of links to display (e.g., '5', '10', '15', '25', 'All')
     * @param componentId - The ID of the component
     * @param showTitle - Whether to show the component title (default: true)
     */
    public static async updateFileComponentSetting(fileComponent: Locator): Promise<void> {
        await fileComponent.locator('label[for$="-compactMode"]').click();
        await fileComponent.locator('label[for$="-showComponentTitle"]').click();
        await fileComponent.locator('input[value="Update"]').click();
        await expect(fileComponent.locator("select[name='size']")).toBeHidden();
    }

    /*
     * Check the description and metadata of a specific link in a links component
     * @param linkComponent - The links component locator
     * @param expectedTitle - The expected title of the link
     * @param expectedDesc - The expected description of the link
     */
    public static async checkFileDescription(
        fileComponent: Locator,
        expectedTitle: string,
        expectedDesc: string,
    ): Promise<void> {
        const REGEX_TIMESTAMP =
            /\b(?:(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s\d{1,2},\s\d{4}\s\d{1,2}:\d{2}\s?(?:am|pm)|(?:just now|\d+\s+(?:second|minute|hour|day|week|month|year)s?\s+ago))\b/i;
        const ELEMENT_METADATA_P = `//h3[normalize-space()='${expectedTitle}']/ancestor::div[contains(@class,'card')]//p`;
        const allTexts = await fileComponent.locator(ELEMENT_METADATA_P).allTextContents();
        const meta = allTexts[0].trim();
        const actualDesc = allTexts[1].trim();
        expect(meta).toContain("Schoolbox Admin");
        expect(meta).toMatch(REGEX_TIMESTAMP);
        expect(actualDesc).toBe(expectedDesc);
    }
}
