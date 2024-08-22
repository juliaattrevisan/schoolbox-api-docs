import { Page } from "@playwright/test";

export default class Resources {
    private page: Page;

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * clicks a folder in the resources page by folder id
     * @param { number } folderId - The folder ID
     */
    async clickFolder(folderId: number) {
        await this.page.locator(`a#${folderId}_anchor`).click();
    }

    /**
     * clicks a folder in the resources page by folder name
     * @param { string } folderName - The folder name
     */
    async clickFolderName(folderName: string) {
        await this.page.locator(`a.jstree-anchor:has-text("${folderName}")`).click();
    }

    /**
     * clicks on the right side menu options to a folder:
     * home, modify, drop-box, files, folder, link, download, upload, delete
     * @param { string } menuItem - The menu item to click
     */
    async clickMenuItem(menuItem: string) {
        await this.page.locator(`#meta a.icon-${menuItem}`).click();
    }
}
