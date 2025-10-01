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
}
