import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page, Locator } from "@playwright/test";
import { expect } from "@playwright/test";

export default class ProfilePage {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/search/user/{id}";

    static readonly DATA_TEST_PROFILE_NAME = "user-profile-name";

    static readonly DATA_TEST_PROFILE_FLAG = "pastoral-flags-{typeId}-{subtypeId}";

    static readonly SELECTOR_PROFILE_TAB = `a[href="#tab-{tabName}"]`;

    static readonly DATA_TEST_PROFILE_BUTTONS = "profile-buttons";

    static readonly DATA_TEST_SHARE_LEARNING_MOMENT_BUTTON = "learning-moment-evidence-share";

    static readonly DATA_TEST_SET_GOALS_BUTTON = "a sbx-icon[name='goal']";

    static readonly SELECTOR_AWARD_ACHIEVEMENTS_BUTTON = "a sbx-icon[name='achievement']";

    static readonly SELECTOR_LEARNING_MOMENTS_FILTER = 'label[for="filter-learningMoments"]';

    static readonly SELECTOR_GOALS_FILTER = 'label[for="filter-goals"]';

    static readonly SELECTOR_ACHIEVEMENTS_FILTER = 'label[for="filter-achievements"]';

    static readonly SELECTOR_FEEDBACK_FILTER = 'label[for="filter-feedback"]';

    static readonly DATA_TEST_PROFILE_DOT_BUTTON = "sbx-pop-out-header-options-button";

    static readonly DATA_TEST_PROFILE_DOT_MENU = "profile-header-options";

    static readonly DATA_TEST_DOT_MENU_OPTIONS = "user-profile-{option}";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to the profile page
     * @param id - The user id
     */
    async goto(id: number): Promise<void> {
        await this.page.goto(ProfilePage.URL.replace("{id}", id.toString()));
    }

    /**
     * click one of the buttons in the profile page
     * options: eportfolio, due-work, tick, pastoral, calendar, timetable, document
     * @param buttonName - The button name
     */
    async clickButton(buttonName: string): Promise<void> {
        const buttons = this.page.getByTestId(ProfilePage.DATA_TEST_PROFILE_BUTTONS);
        await buttons.locator("a").filter({ hasText: buttonName }).click();
    }

    /**
     * click one of the tabs in the profile page
     * options: activity, goals, achievements, classes, groups, contacts
     * @param tabName - The tab name
     */
    async clickTab(tabName: string): Promise<void> {
        await this.page.click(`a[href="#tab-${tabName}"]`);
    }

    /**
     * click a Badge by passing its name
     * or
     * click a dropdown option: Remove goal, Award achievement, Withdraw achievement
     * @param badgeNameOrDropdownOption - The badge name or dropdown option
     */
    async clickBadgeOrDropdownOption(badgeNameOrDropdownOption: string): Promise<void> {
        await this.page.locator(`a[title="${badgeNameOrDropdownOption}"]`).last().click();
    }

    /**
     * click a button in the Badge modal dialog
     * options: Award
     * @param buttonName - The button name
     */
    async clickButtonInModal(buttonName: string): Promise<void> {
        await this.page.locator("button").filter({ hasText: buttonName }).click();
    }

    /**
     * Click a badge dropdown
     * @param badgeId - The badge number
     */
    async clickBadgeDropdown(badgeId: number): Promise<void> {
        await this.page.getByTestId(`sbx-pop-out-header-options-1-${badgeId}-goal`).click();
    }

    /**
     * Click a badge achievement in the dropdown
     * @param { number } badgeId - The badge number
     */

    async clickBadgeAchievementDropdown(badgeId: number): Promise<void> {
        await this.page.getByTestId(`sbx-pop-out-header-options-1-${badgeId}-achievement`).click();
    }
    /**
     * Sets visibility
     * @param fieldName Check the field name is presented
     */

    async checkFieldNameIsPresented(fieldName: string): Promise<void> {
        await expect(this.page.getByTestId(ProfilePage.DATA_TEST_PROFILE_NAME)).toHaveText(fieldName);
    }

    /**
     * Returns the locator for the flag
     * @param typeId - The type id
     * @param subTypeId - The sub type id
     * @returns - The locator for the flag
     */
    getFlag(typeId: number, subTypeId: number): Locator {
        return this.page.getByTestId(
            ProfilePage.DATA_TEST_PROFILE_FLAG.replace("{typeId}", typeId.toString()).replace(
                "{subtypeId}",
                subTypeId.toString(),
            ),
        );
    }

    /**
     * Verify if a specified field is visible on profile page
     * @param fieldName - The name of the field to check (e.g., "Role", "Campus", "Class Profile")
     */
    async checkFieldIsVisible(fieldName: string): Promise<void> {
        const fieldLocator = this.page.locator(`dl.profile dt`, { hasText: fieldName });
        await expect(fieldLocator).toBeVisible();
    }

    /**
     * Verify if a specified field is NOT visible on profile page
     * @param fieldName - The name of the field to check (e.g., "Role", "Campus", "Class Profile")
     */
    async checkFieldIsNotVisible(fieldName: string): Promise<void> {
        const fieldLocator = this.page.locator(`dl.profile dt`, { hasText: fieldName });
        await expect(fieldLocator).not.toBeVisible();
    }

    /**
     * Verify if a specified tab is visible on profile page
     * @param tabName - The name of the tab to check (e.g., "Goals", "Achievements", "Classes")
     */
    async checkTabIsVisible(tabName: string): Promise<void> {
        const tabLocator = this.page.locator(ProfilePage.SELECTOR_PROFILE_TAB.replace("{tabName}", tabName));
        await expect(tabLocator).toBeVisible();
    }

    /**
     * Verify if a specified tab is NOT visible on profile page
     * @param tabName - The name of the tab to check (e.g., "Goals", "Achievements", "Classes")
     */
    async checkTabIsNotVisible(tabName: string): Promise<void> {
        const tabLocator = this.page.locator(ProfilePage.SELECTOR_PROFILE_TAB.replace("{tabName}", tabName));
        await expect(tabLocator).not.toBeVisible();
    }

    /**
     * Verify if a specified image is presented on profile page
     * @param userId - The user id to check (e.g., "0", "22")
     */
    async checkImagePresence(userId: string | number): Promise<void> {
        const imageSrc = await this.page.locator("img.full-width").getAttribute("src");
        expect(imageSrc).toContain(`id=${userId}`);
    }

    /**
     * Returns an action from the opened profile page 3-dot menu by locating the data-test attribute
     * @param actionDataTest - The data-test attribute value of the action: modify, impersonate, create-pastoral-record
     * @returns - The profile page 3-dot menu action
     */
    getDotMenuAction(actionDataTest: string): Locator {
        return this.page
            .getByTestId(ProfilePage.DATA_TEST_PROFILE_DOT_MENU)
            .getByTestId(ProfilePage.DATA_TEST_DOT_MENU_OPTIONS.replace("{option}", actionDataTest));
    }

    /**
     * Opens the profile page 3-dot menu.
     */
    async openDotMenu(): Promise<void> {
        await this.page.getByTestId(ProfilePage.DATA_TEST_PROFILE_DOT_BUTTON).click();
        await expect(this.page.getByTestId(ProfilePage.DATA_TEST_PROFILE_DOT_MENU)).toBeVisible();
    }

    /**
     * Checks the profile page 3-dot menu is not presented.
     */
    async checkDotMenuIsNotPresented(): Promise<void> {
        await expect(this.page.getByTestId(ProfilePage.DATA_TEST_PROFILE_DOT_BUTTON)).toBeHidden();
    }

    /**
     * Check whether or not the 3-dot menu items are visible
     * options: modify, impersonate, create-pastoral-record
     * e.g.: { option: "modify", isVisible: true }
     * @param options - The array of options to check with the name and visibility
     */
    async checkDotMenuOptionsVisibility(options: { option: string; isVisible: boolean }[]): Promise<void> {
        for (const option of options) {
            if (option.isVisible) {
                await expect(
                    this.page.getByTestId(ProfilePage.DATA_TEST_DOT_MENU_OPTIONS.replace("{option}", option.option)),
                ).toBeVisible();
            } else {
                await expect(
                    this.page.getByTestId(ProfilePage.DATA_TEST_DOT_MENU_OPTIONS.replace("{option}", option.option)),
                ).toBeHidden();
            }
        }
    }

    /**
     * Checks an action in the opened profile page 3-dot menu links to the expected url.
     * @param actionName - The action name: modify, impersonate, create-pastoral-record
     * @param expectedUrl - The expected href
     */
    async checkDotMenuActionUrl(actionName: string, expectedUrl: RegExp): Promise<void> {
        await expect(this.getDotMenuAction(actionName)).toHaveAttribute("href", expectedUrl);
    }

    /**
     * Clicks the 3-dot menu action and checks the impersonate button is disabled
     */
    async checkImpersonateIsDisabled(): Promise<void> {
        await this.openDotMenu();
        await expect(
            this.page.getByTestId(ProfilePage.DATA_TEST_DOT_MENU_OPTIONS.replace("{option}", "impersonate-disabled")),
        ).toBeVisible();
        await expect(
            this.page.getByTestId(ProfilePage.DATA_TEST_DOT_MENU_OPTIONS.replace("{option}", "impersonate-disabled")),
        ).not.toHaveAttribute("href", /.*/);
    }

    /**
     * Checks a single profile header action button is visible and links to the expected url.
     * @param actionName - The action name
     * @param expectedUrl - The expected href
     */
    async checkSingleHeaderActionButton(actionName: string, expectedUrl: RegExp): Promise<void> {
        const button = this.page.locator(".button-action a").filter({ hasText: actionName });
        await expect(button).toBeVisible();
        await expect(button).toHaveAttribute("href", expectedUrl);
    }
}
