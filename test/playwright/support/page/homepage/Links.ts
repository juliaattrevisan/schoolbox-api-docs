import { expect } from "@playwright/test";
import type { Locator, Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class Links {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly ELEMENT_TITLE_TEXTBOX = 'input[id="description"]';

    static readonly ELEMENT_URL_TEXTBOX = 'input[id="url"]';

    static readonly ELEMENT_DESCRIPTION_TEXTBOX = 'textarea[id="comment"]';

    static readonly ELEMENT_OPEN_IN_SELECT = 'select[name="target"]';

    static readonly ELEMENT_VISIBILITY_CHECKBOX = 'label[for="visible"]';

    static readonly ELEMENT_ADD_LINK_BUTTON = 'input[value="Add Link"]';

    static readonly ELEMENT_LINK_TITLE_TRANSLUCENT = 'div.translucent a[title="{linkTitle}"]';

    static readonly ELEMENT_LINK_TITLE_NONTRANSLUCENT = 'div a[title="{linkTitle}"]';

    static readonly ELEMENT_LINKS = '[class="action-list"] li h3 a';

    static readonly ELEMENT_ADD_LINK_BUTTON_IN_COMPONENT = `a[href="/cms/link/new/{homepageId}"]`;

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Check if a specific link exists in a links component
     * @param component - The links component
     * @param name - The name of the link shown to the user
     */
    public async checkLinkExists(component: Locator, name: string): Promise<void> {
        this.tester.isGoingTo(`Check the link called ${name}`);
        await expect(component.locator("h3 a", { hasText: name })).toBeVisible();
    }

    /**
     * Clicking add link button in a link component
     * @param component - The links component
     */
    public async clickAddLink(component: Locator): Promise<void> {
        this.tester.isGoingTo(`Click add link button`);
        await component.getByTestId("add-link-button").click();
    }

    /**
     * Open the “View All Links” page from a Links component.
     * Waits for the /resources/:id navigation to complete.
     * @param component - The links component
     */
    public async clickViewAllLinks(component: Locator): Promise<void> {
        this.tester.isGoingTo('Click "View All Links"');
        await component.getByTestId("view-all-links-button").click();
    }

    /**
     *
     * @param linkComponent - The links component
     * @param title - The title of the link
     * @param url - The url of the link
     * @param description - The description of the link
     * @param expectedVisiblity - The expected visibility of the link (true for visible, false for hidden)
     */
    public async addLink(
        linkComponent: Locator,
        title: string,
        url: string,
        description: string,
        expectedVisiblity: boolean,
        openInNewWindow: boolean = false,
    ): Promise<void> {
        this.tester.isGoingTo("Add a tile , add a url, add a Description and select visibility and Click add link");
        await this.clickAddLink(linkComponent);
        await this.page.fill(Links.ELEMENT_TITLE_TEXTBOX, title);
        await this.page.fill(Links.ELEMENT_URL_TEXTBOX, url);
        await this.page.fill(Links.ELEMENT_DESCRIPTION_TEXTBOX, description);
        await this.page.selectOption(Links.ELEMENT_OPEN_IN_SELECT, openInNewWindow ? "_blank" : "");
        const actualVisibility = await this.page.isChecked(Links.ELEMENT_VISIBILITY_CHECKBOX); // Default from UI
        if (expectedVisiblity && !actualVisibility) {
            // User want to keep the link visible but by default the checkbix was marked hidden
            await this.page.click(Links.ELEMENT_VISIBILITY_CHECKBOX);
        } else if (!expectedVisiblity && actualVisibility) {
            // User want to keep the link hidden but by default the checkbix was marked visible
            await this.page.click(Links.ELEMENT_VISIBILITY_CHECKBOX);
        }
        await this.page.click(Links.ELEMENT_ADD_LINK_BUTTON);
    }

    /*
     * Verify if a link is present in a links component
     * @param linkComponent - The links component locator
     * @param linkTitle - The title of the link
     * @param translucent - Whether the component is in translucent mode (default: false)
     */
    public static async verifyLink(
        linkComponent: Locator,
        linkTitle: string,
        translucent: boolean = false,
    ): Promise<void> {
        if (translucent) {
            await expect(
                linkComponent.locator(Links.ELEMENT_LINK_TITLE_TRANSLUCENT.replace("{linkTitle}", linkTitle)),
            ).toBeVisible();
        } else {
            await expect(
                linkComponent.locator(Links.ELEMENT_LINK_TITLE_NONTRANSLUCENT.replace("{linkTitle}", linkTitle)),
            ).toBeVisible();
        }
    }

    /**
     *
     * @param linkComponent - The links component locator
     * @param sortBy - The criteria to sort by (e.g., 'Name' or 'Date')
     * @param sortingOrder - The order of sorting (e.g., 'Ascending' or 'Descending')
     */
    public static async sortLinks(linkComponent: Locator, sortBy: string, sortingOrder: string): Promise<void> {
        await linkComponent.locator('select[name="sort"]').selectOption(sortBy);
        await linkComponent.locator('select[name="sortdir"]').selectOption(sortingOrder);
        await linkComponent.locator('input[value="Update"]').click();
        await expect(linkComponent.locator('select[name="sort"]')).toBeHidden();
    }

    /**
     *
     * @param linkComponent - The links component locator
     * @param Limit - The number of links to display (e.g., '5', '10', '15', '25', 'All')
     */
    public static async setLinkLimit(linkComponent: Locator, Limit: string): Promise<void> {
        await linkComponent.locator("select[name='size']").selectOption(Limit);
        await linkComponent.locator('input[value="Update"]').click();
        await expect(linkComponent.locator('select[name="sort"]')).toBeHidden();
    }

    /**
     *
     * @param linkComponent - The links component locator
     * @param limit - The number of links to display (e.g., '5', '10', '15', '25', 'All')
     * @param componentId - The ID of the component
     * @param showTitle - Whether to show the component title (default: true)
     */
    public static async updateLinkComponentSetting(
        linkComponent: Locator,
        limit: string,
        componentId: number,
        showTitle: boolean = true,
    ): Promise<void> {
        await linkComponent.locator("select[name='size']").selectOption(limit);
        await linkComponent.locator('label[for$="-compactMode"]').click();
        if (showTitle) await linkComponent.locator(`label[for="component${componentId}-showComponentTitle"]`).click();
        await linkComponent.locator('input[value="Update"]').click();
        await expect(linkComponent.locator("select[name='size']")).toBeHidden();
    }

    /*
     * Check the description and metadata of a specific link in a links component
     * @param linkComponent - The links component locator
     * @param expectedTitle - The expected title of the link
     * @param expectedDesc - The expected description of the link
     */
    public static async checkDescription(
        linkComponent: Locator,
        expectedTitle: string,
        expectedDesc: string,
    ): Promise<void> {
        const REGEX_TIMESTAMP = /([A-Za-z]{3}\s\d{1,2},\s\d{4}\s\d{1,2}:\d{2}(?:am|pm))/;
        const ELEMENT_METADATA_P = `//a[text()='${expectedTitle}']//ancestor::div[contains(@class,'card')]//p`;
        const allTexts = await linkComponent.locator(ELEMENT_METADATA_P).allTextContents();
        const meta = allTexts[0].trim();
        const actualDesc = allTexts[1].trim();
        expect(meta).toContain("Schoolbox Admin");
        expect(meta).toMatch(REGEX_TIMESTAMP);
        expect(actualDesc).toBe(expectedDesc);
    }

    /*
     * Retrieve all link titles from a links component
     * @param linkComponent - The links component locator
     * @returns An array of link titles
     */
    public static async getAllLinks(linkComponent: Locator): Promise<string[]> {
        const linksLocator = linkComponent.locator(Links.ELEMENT_LINKS);
        const linktexts = await linksLocator.allTextContents();
        return linktexts;
    }

    public static getLink(linkComponent: Locator, linkTitle: string): Locator {
        return linkComponent.locator(`h3 a[title='${linkTitle}']`);
    }

    /*
     * Add an existing link from the file system to a links component
     * @param linkComponent - The links component locator
     * @param srcFolder - The source folder name
     * @param subSrcFolder - The sub-source folder name
     */
    public async addExistingLink(linkComponent: Locator, srcFolder: string, subSrcFolder: string): Promise<void> {
        const regexSrc = new RegExp(`^${srcFolder}$`);
        await linkComponent.locator('[id*="modalLinkButton"]', { hasText: "Browse" }).click();
        await this.page.locator("#filesystem-browse-panel a", { hasText: regexSrc }).click();
        const locatorEng9A = this.page.locator("#filesystem-browse-panel li.resource a", { hasText: subSrcFolder });
        await locatorEng9A.click({ delay: 1000 });
        await expect(locatorEng9A).toBeHidden();
        await this.page
            .locator("#filesystem-browse-panel div.component-action a", { hasText: "Use This Folder" })
            .click();
        const locatorEng = linkComponent.locator("div.source a", { hasText: subSrcFolder });
        await expect(locatorEng).toBeVisible();
        await linkComponent.locator('input[type="submit"][value="Update"]').click();
        await expect(locatorEng).toBeHidden();
    }

    /*
     * Delete a link previously created
     * @param linkComponent - The links component
     * @param linkTitle - The title of the link to be deleted
     */
    public async deleteLink(linkComponent: Locator, linkTitle: string): Promise<void> {
        await linkComponent.locator(`a[title='${linkTitle}']`).click();
        await this.page.getByTestId("sbx-pop-out-header-options-button").click();
        await this.page.getByText("Delete Link").click();
        await this.page.getByTestId("confirm-dialog-confirm-button").click();
    }

    /*
     * Modify a link previously created
     * @param linkComponent - The links component
     * @param linkId - The id of the link to be modified from table link
     * @param newlinkTitle - The new title for the link
     */
    public async modifyLink(linkComponent: Locator, linkId: number, newlinkTitle: string): Promise<void> {
        await linkComponent.locator(`a[href='/cms/link/${linkId}']`).click();
        await this.page.locator("#description").fill(newlinkTitle);
        await this.page.locator('input[value="Modify Link"]').click();
        await expect(this.page.locator("#description")).toBeHidden();
    }
}
