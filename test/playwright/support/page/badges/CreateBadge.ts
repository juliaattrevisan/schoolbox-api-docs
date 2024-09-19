import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { Page, expect } from "@playwright/test";
import Badges from "@schoolboxTest/playwright/support/page/badges/Badges";

export default class CreateBadge {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly BADGE_NAME = "#badge-name";

    static readonly BROWSE_ICON_BUTTON = 'a[data-reveal-id="icon-modal"]';

    static readonly MODAL = "div#icon-modal";

    static readonly CATEGORY_DROPDOWN = "#badge-category";

    static readonly CREATE_BADGE_BUTTON = "#badge-submit";

    static readonly CKEDITOR_TOOL_BAR = "div[class='ck ck-toolbar__items']";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * click Create Badge button
     * @param badgeName - The badge name
     */
    async typeName(badgeName: string): Promise<void> {
        await this.page.fill(CreateBadge.BADGE_NAME, badgeName);
    }

    /**
     * click Browse icon library button
     */
    async clickBrowseIconLibraryButton(): Promise<void> {
        await expect(this.page.locator(CreateBadge.CKEDITOR_TOOL_BAR)).toBeVisible();
        await this.page.locator(CreateBadge.BROWSE_ICON_BUTTON).click();
    }

    /**
     * wait appearance of the modal dialog
     * @throws \Exception
     */

    async waitModalDialogPresented(): Promise<void> {
        await this.page.waitForSelector(CreateBadge.MODAL);
    }

    /**
     * click a vertical Tab
     * @param title - The title of the vertical tab
     */
    async clickVerticalTabItem(title: string): Promise<void> {
        await this.page.click(`li[data-icon-path=${title}]`);
    }

    /**
     * click one of the badges images
     * @param image - The image name
     */
    async clickBadgeImage(image: string): Promise<void> {
        await this.page.click(`img[src$="/images/badges/${image}.svg"]`);
    }

    /**
     * click category
     */

    async clickCategoryDropDown(): Promise<void> {
        await expect(this.page.locator(CreateBadge.CKEDITOR_TOOL_BAR)).toBeVisible();
        await this.page.click(CreateBadge.CATEGORY_DROPDOWN);
    }

    /**
     * click one of the categories
     * @param category - The category name
     */
    async clickCategoryItem(category: string): Promise<void> {
        await this.page.locator("#badge-category").selectOption({ label: category });
    }

    /**
     * click Create Badge button
     */

    async clickCreateBadgeButton(): Promise<void> {
        await this.page.click(CreateBadge.CREATE_BADGE_BUTTON);
    }

    /**
     * create a Badge
     * @param tester - The tester
     * @param page - The page
     * @param badgeName - The badge name
     */
    async createBadge(tester: AcceptanceTester, page: Page, badgeName: string): Promise<void> {
        const badges = new Badges(tester, page);

        const category = "Learning";
        await badges.clickAddBadgeButton();
        await this.typeName(badgeName);
        await this.clickBrowseIconLibraryButton();
        await this.waitModalDialogPresented();
        await this.clickVerticalTabItem("food");
        await this.clickBadgeImage("food/1");
        await this.clickCategoryDropDown();
        await this.clickCategoryItem(category);
        await this.clickCreateBadgeButton();
        await badges.waitAddBadgeButtonToBePresented();
        await badges.verifyTextIsPresented(badgeName);
    }
}
