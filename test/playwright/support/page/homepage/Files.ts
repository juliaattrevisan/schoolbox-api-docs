import { expect } from "@playwright/test";
import type { Locator, Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type DatabaseHelper from "@schoolboxTest/playwright/support/DatabaseHelper";

export default class Files {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly ELEMENT_FILE_TITLE_TEXTBOX = "#description";

    static readonly ELEMENT_FILE_DESCRIPTION_TEXTBOX = "#comment";

    static readonly ELEMENT_POST_FILE_BUTTON = 'input[value="Post File"]';

    static readonly ELEMENT_SELECTOR_UPDATE_BUTTON = 'input[value="Update"]';

    static readonly ELEMENT_SELECTOR_COMPONENTNAME_INPUT = 'input[name="componentname"]';

    static readonly ELEMENT_ATTACH_FILE = "#CmsZone";

    static readonly ELEMENT_MY_FILES_LINK = 'a.icon-files[href="#filesystem-myfile-panel"]';

    static readonly ELEMENT_FILE_VISIBILITY_CHECKBOX_INPUT = "#visible";

    static readonly ELEMENT_FILE_VISIBILITY_CHECKBOX_LABEL = 'label[for="visible"]';

    static readonly DATA_TEST_FILE_MODIFY = "file-list-modify-icon-{fileId}";

    static readonly DATA_TEST_FILE_SELECT_LINK = "file-select-link";

    static readonly DATA_TEST_VIEW_ALL_FILES_BUTTON = "view-all-files-button";

    static readonly DATA_TEST_PREVIEW_FILES = /^file-component-title-/;

    static readonly ELEMENT_SELECTOR_SORT = 'select[name="sort"]';

    static readonly ELEMENT_SELECTOR_SORTDIR = 'select[name="sortdir"]';

    static readonly ELEMENT_SELECTOR_SIZE = "select[name='size']";

    static readonly ELEMENT_FILTER_DOCUMENT = 'label[for$="-filter-document"]';

    static readonly ELEMENT_FILTER_AUDIO = 'label[for$="-filter-audio"]';

    static readonly ELEMENT_FILTER_VIDEO = 'label[for$="-filter-video"]';

    static readonly ELEMENT_FILTER_IMAGE = 'label[for$="-filter-image"]';

    static readonly ELEMENT_FILTER_OTHER = 'label[for$="-filter-other"]';

    static readonly ELEMENT_FILE_TILE_TITLE_TRANSLUCENT =
        'div.translucent a[data-plugin="preview"][title="{fileTitle}"] h3';

    static readonly ELEMENT_FILE_TILE_TITLE_NONTRANSLUCENT =
        'div:not(.translucent) a[data-plugin="preview"][title="{fileTitle}"] h3';

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
                container.locator(Files.ELEMENT_FILE_TILE_TITLE_TRANSLUCENT.replace("{fileTitle}", fileTitle)),
            ).toBeVisible();
        } else {
            await expect(
                container.locator(Files.ELEMENT_FILE_TILE_TITLE_NONTRANSLUCENT.replace("{fileTitle}", fileTitle)),
            ).toBeVisible();
        }
    }

    /*
     * Add/post a file into the Files component.
     *
     * @param page - Playwright Page.
     * @param component - Root locator for this Files component.
     * @param fileTitle - Title to enter in the form.
     * @param description - Description to enter in the form.
     * @param expectedVisibility - Whether the file should be visible after posting.
     * @param db - The DatabaseHelper instance
     * @param filePath - The file to attach
     */
    public async selectFile(
        component: Locator,
        fileTitle: string,
        description: string,
        expectedVisibility: boolean,
        db: DatabaseHelper,
        filePath: string,
    ): Promise<void> {
        await component.getByTestId(Files.DATA_TEST_FILE_SELECT_LINK).click();
        await this.page.fill(Files.ELEMENT_FILE_TITLE_TEXTBOX, fileTitle);
        await this.page.fill(Files.ELEMENT_FILE_DESCRIPTION_TEXTBOX, description);
        const attachZone = this.page.locator(Files.ELEMENT_ATTACH_FILE);
        // Attach the file using your drag-and-drop helper
        await this.tester.dragAndDropFile(db, attachZone, filePath);
        // Ensure visibility matches expectation by toggling via label
        const actualVisibility = await this.page.isChecked(Files.ELEMENT_FILE_VISIBILITY_CHECKBOX_INPUT);
        if (expectedVisibility && !actualVisibility) {
            await this.page.click(Files.ELEMENT_FILE_VISIBILITY_CHECKBOX_LABEL);
        } else if (!expectedVisibility && actualVisibility) {
            await this.page.click(Files.ELEMENT_FILE_VISIBILITY_CHECKBOX_LABEL);
        }
        await this.page.click(Files.ELEMENT_POST_FILE_BUTTON);
    }

    /**
     * Filter to images only, then save.
     * @param filesComponent - Root locator for the Files component.
     */
    public static async sortImageFiles(filesComponent: Locator): Promise<void> {
        await filesComponent.locator(Files.ELEMENT_FILTER_DOCUMENT).click();
        await filesComponent.locator(Files.ELEMENT_FILTER_AUDIO).click();
        await filesComponent.locator(Files.ELEMENT_FILTER_VIDEO).click();
        await filesComponent.locator(Files.ELEMENT_FILTER_OTHER).click();
        await filesComponent.locator(Files.ELEMENT_SELECTOR_UPDATE_BUTTON).click();
        await expect(filesComponent.locator(Files.ELEMENT_SELECTOR_SORT)).toBeHidden();
    }

    /**
     * Filter to video + document, then save.
     * @param filesComponent - Root locator for the Files component.
     */
    public static async sortVideoAndDocumentFiles(filesComponent: Locator): Promise<void> {
        await filesComponent.locator(Files.ELEMENT_FILTER_DOCUMENT).click();
        await filesComponent.locator(Files.ELEMENT_FILTER_VIDEO).click();
        await filesComponent.locator(Files.ELEMENT_FILTER_IMAGE).click();
        await filesComponent.locator(Files.ELEMENT_SELECTOR_UPDATE_BUTTON).click();
        await expect(filesComponent.locator(Files.ELEMENT_SELECTOR_SORT)).toBeHidden();
    }

    /*
     * Retrieve all file titles from a files component.
     * @param filesComponent - Root locator for the Files component.
     * @returns Array of file titles.
     */
    public static async getAllFiles(filesComponent: Locator): Promise<string[]> {
        const fileTexts = await filesComponent.getByTestId(Files.DATA_TEST_PREVIEW_FILES).allTextContents();
        return fileTexts.map((text) => text.trim());
    }

    /**
     * Apply sorting + update.
     * @param filesComponent - Root locator for the Files component.
     * @param sortBy - e.g. 'name' | 'date'
     * @param sortingOrder - e.g. 'asc' | 'desc'
     */
    public static async sortFilesAsc(filesComponent: Locator, sortBy: string, sortingOrder: string): Promise<void> {
        await filesComponent.locator(Files.ELEMENT_FILTER_AUDIO).click();
        await filesComponent.locator(Files.ELEMENT_FILTER_IMAGE).click();
        await filesComponent.locator(Files.ELEMENT_FILTER_OTHER).click();
        await filesComponent.locator(Files.ELEMENT_SELECTOR_SORT).selectOption(sortBy);
        await filesComponent.locator(Files.ELEMENT_SELECTOR_SORTDIR).selectOption(sortingOrder);
        await filesComponent.locator(Files.ELEMENT_SELECTOR_UPDATE_BUTTON).click();
        await expect(filesComponent.locator(Files.ELEMENT_SELECTOR_SORT)).toBeHidden();
    }

    /**
     * Only change sort fields (no filter toggles) + update.
     * @param filesComponent - Root locator for the Files component.
     * @param sortBy - e.g. 'name' | 'date'
     * @param sortingOrder - e.g. 'asc' | 'desc'
     */
    public static async sortFilesDesc(filesComponent: Locator, sortBy: string, sortingOrder: string): Promise<void> {
        await filesComponent.locator(Files.ELEMENT_SELECTOR_SORT).selectOption(sortBy);
        await filesComponent.locator(Files.ELEMENT_SELECTOR_SORTDIR).selectOption(sortingOrder);
        await filesComponent.locator(Files.ELEMENT_SELECTOR_UPDATE_BUTTON).click();
        await expect(filesComponent.locator(Files.ELEMENT_SELECTOR_SORT)).toBeHidden();
    }

    /**
     * Set the "limit" (page size) and save.
     * @param fileComponent - Root locator for the Files component.
     * @param limit - '1' | '2' | 'All'
     */
    public static async setFileLimit(fileComponent: Locator, limit: string): Promise<void> {
        await fileComponent.locator(Files.ELEMENT_SELECTOR_SIZE).selectOption(limit);
        await fileComponent.locator(Files.ELEMENT_SELECTOR_UPDATE_BUTTON).click();
        await expect(fileComponent.locator(Files.ELEMENT_SELECTOR_SORT)).toBeHidden();
    }

    /**
     * @param fileComponent - Root locator for this Files component; actions are scoped to it.
     *   - Tick compact mode
     *   - Tick show component titleAbby
     *   - Clicks Update and waits for the form to close
     */
    public static async updateFileComponentSetting(fileComponent: Locator): Promise<void> {
        await fileComponent.locator('label[for$="-compactMode"]').click();
        await fileComponent.locator('label[for$="-showComponentTitle"]').click();
        await fileComponent.locator(Files.ELEMENT_SELECTOR_UPDATE_BUTTON).click();
        await expect(fileComponent.locator(Files.ELEMENT_SELECTOR_SIZE)).toBeHidden();
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
        //await fileComponent.locator(`a.icon-modify[title="Modify File"][href^="/cms/file/${fileId}"]`).click();
        //await fileComponent.getByTestId(`${Files.ELEMENT_MODIFY_FILE}-${fileId}`).click();
        //await fileComponent.getByTestId(`file-card-${fileId}`).getByTestId(Files.ELEMENT_MODIFY_FILE).click();
        // await fileComponent.getByTestId(Files.DATA_TEST_FILE_MODIFY).click();
        await fileComponent.getByTestId(Files.DATA_TEST_FILE_MODIFY.replace("{fileId}", String(fileId))).click();

        await this.page.locator(Files.ELEMENT_FILE_TITLE_TEXTBOX).fill(newFileTitle);
        await this.page.locator('input[value="Modify File"]').click();
        await expect(this.page.locator(Files.ELEMENT_FILE_TITLE_TEXTBOX)).toBeHidden();
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
        await fileComponent.locator(Files.ELEMENT_SELECTOR_UPDATE_BUTTON).click();
        await expect(locatorEng).toBeHidden();
    }

    /**
     * Update the Files component heading and verify the breadcrumb on the resources page.
     * @param page - Playwright Page.
     * @param filesComponent - Root locator for this Files component.
     * @param classPageId - The homepage/class ID (e.g., 83).
     * @param newHeading - New heading to set (default: "Automation Heading Test").
     */
    public static async setHeadingAndVerifyBreadcrumb(
        page: Page,
        filesComponent: Locator,
        classPageId: number,
        newHeading: string = "Automation Heading Test",
    ): Promise<void> {
        await filesComponent.locator(Files.ELEMENT_SELECTOR_COMPONENTNAME_INPUT).fill(newHeading);
        await filesComponent.locator(Files.ELEMENT_SELECTOR_UPDATE_BUTTON).click();
        await filesComponent.getByTestId(Files.DATA_TEST_VIEW_ALL_FILES_BUTTON).click();

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
        await filesComponent.locator(Files.ELEMENT_SELECTOR_COMPONENTNAME_INPUT).fill("");
        await filesComponent.locator(Files.ELEMENT_SELECTOR_UPDATE_BUTTON).click();
        await filesComponent.getByTestId(Files.DATA_TEST_VIEW_ALL_FILES_BUTTON).click();
        await expect(page).toHaveURL(new RegExp(`/resources/folder/\\d+/files/${classPageId}$`));
        await expect(page.locator("ul.breadcrumb > li.active > span")).toContainText(expectedHeading);
    }
}
