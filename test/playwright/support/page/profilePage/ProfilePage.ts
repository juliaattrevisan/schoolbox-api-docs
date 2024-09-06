import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { Page } from "@playwright/test";

export default class ProfilePage {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/search/user/{id}";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to the profile page
     * @param { string } id - The user id
     */
    async goto(id: string) {
        await this.page.goto(ProfilePage.URL.replace("{id}", id.toString()));
    }

    /**
     * click one of the buttons in the profile page
     * options: eportfolio, due-work, tick, pastoral, calendar, timetable, document
     * @param { string } buttonName - The button name
     */
    async clickButton(buttonName: string) {
        await this.page.click(`a.icon-${buttonName}`);
    }

    /**
     * click one of the tabs in the profile page
     * options: activity, goals, achievements, classes, groups, contacts
     * @param { string } tabName - The tab name
     */
    async clickTab(tabName: string) {
        await this.page.click(`a[href="#tab-${tabName}"]`);
    }

    /**
     * click a Badge by passing its name
     * or
     * click a dropdown option: Remove goal, Award achievement, Withdraw achievement
     * @param { string } badgeNameOrDropdownOption - The badge name or dropdown option
     */
    async clickBadgeOrDropdownOption(badgeNameOrDropdownOption: string) {
        await this.page.locator(`a[title="${badgeNameOrDropdownOption}"]`).last().click();
    }

    /**
     * click a button in the Badge modal dialog
     * options: Award
     * @param { string } buttonName - The button name
     */
    async clickButtonInModal(buttonName: string) {
        await this.page.locator("button").filter({ hasText: buttonName }).click();
    }

    /**
     * Click a badge dropdown
     * @param { number } badgeId - The badge number
     */
    async clickBadgeDropdown(badgeId: number) {
        await this.page.click(`[data-test=sbx-pop-out-header-options-1-${badgeId}-goal]`);
    }

    /**
     * Click a badge achievement in the dropdown
     * @param { number } badgeId - The badge number
     */

    async clickBadgeAchievementDropdown(badgeId: number) {
        await this.page.click(`[data-test=sbx-pop-out-header-options-1-${badgeId}-achievement]`);
    }
}
