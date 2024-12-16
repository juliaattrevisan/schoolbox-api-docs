import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";

export default class Resources {
    private page: Page;

    static readonly RESOURCE_BROWSER = ".resource-browser";

    /**
     * cancel button on modal dialogs
     */
    static readonly MODAL_CANCEL_BUTTON = "a#confirm-dialog-cancel-button.button";

    static readonly MODAL_DELETE_BUTTON = "a#confirm-dialog-confirm-button.button";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Asserts if a file is visible in the resources page
     * @param fileName - The file title
     */
    async seeFile(fileName: string): Promise<void> {
        const file = this.page.locator(`${Resources.RESOURCE_BROWSER} a.jstree-anchor`).filter({ hasText: fileName });
        await expect(file).toBeVisible();
    }

    /**
     * clicks a folder in the resources page by folder id
     * @param folderId - The folder ID
     */
    async clickFolder(folderId: number): Promise<void> {
        // Need to use [id=""] since the ID starts with a number
        await this.page.locator(`${Resources.RESOURCE_BROWSER} a[id="${folderId}_anchor"]`).click();
    }

    /**
     * clicks a folder in the resources page by folder name
     * @param folderName - The folder name
     */
    async clickFolderName(folderName: string): Promise<void> {
        await this.page
            .locator(`${Resources.RESOURCE_BROWSER} a.jstree-anchor`)
            .filter({ hasText: folderName })
            .click();
        await expect(this.page.locator(`${Resources.RESOURCE_BROWSER} .meta-preview h3`)).toContainText(folderName);
    }

    /**
     * clicks on the right side menu options to a folder:
     * home, modify, drop-box, files, folder, link, download, upload, delete
     * @param menuItem - The menu item to click
     */
    async clickMenuItem(menuItem: string): Promise<void> {
        await this.page.locator(`${Resources.RESOURCE_BROWSER} #meta a.icon-${menuItem}`).click();
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
