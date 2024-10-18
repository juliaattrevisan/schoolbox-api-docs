import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";

export default class Resources {
    private page: Page;

    /**
     * cancel button on modal dialogs
     */
    static readonly MODAL_CANCEL_BUTTON = "a#confirm-dialog-cancel-button.button";

    static readonly MODAL_DELETE_BUTTON = "a#confirm-dialog-confirm-button.button";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * clicks a folder in the resources page by folder id
     * @param folderId - The folder ID
     */
    async clickFolder(folderId: number): Promise<void> {
        await this.page.locator(`a#${folderId}_anchor`).click();
    }

    /**
     * clicks a folder in the resources page by folder name
     * @param folderName - The folder name
     */
    async clickFolderName(folderName: string): Promise<void> {
        await this.page.locator(`a.jstree-anchor:has-text("${folderName}")`).click();
        await expect(this.page.locator(`.meta-preview h3`)).toContainText(folderName);
    }

    /**
     * clicks on the right side menu options to a folder:
     * home, modify, drop-box, files, folder, link, download, upload, delete
     * @param menuItem - The menu item to click
     */
    async clickMenuItem(menuItem: string): Promise<void> {
        await this.page.locator(`#meta a.icon-${menuItem}`).click();
    }

    /**
     * click the last element to confirm deletion in a modal dialog
     */
    async clickModalDeleteButton(): Promise<void> {
        await this.page.locator(Resources.MODAL_DELETE_BUTTON).last().click();
    }

    /**
     * click the last element to Cancel confirm deletion in a modal dialog
     */
    async clickModalCancelButton(): Promise<void> {
        await this.page.locator(Resources.MODAL_CANCEL_BUTTON).last().click();
    }
}
