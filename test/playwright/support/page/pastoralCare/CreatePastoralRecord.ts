import type { Page } from "@playwright/test";
import { expect } from "@playwright/test";

export default class CreatePastoralRecord {
    // Playwright page instance used to interact with the pastoral record form.
    private page: Page;

    // Route template for creating a new pastoral record for a student.
    static readonly URL = "/pastoral/student/{studentId}/record/insert";

    // Route template for editing an existing pastoral record.
    static readonly URL_EXISTING = "pastoral/student/{studentId}/record/{recordId}/update";

    // Selector for the pastoral record title input field.
    static readonly PASTORAL_TITLE = "input#heading";

    // Selector for the pastoral record type dropdown.
    static readonly PASTORAL_TYPE = "select#typeId";

    // Selector for the pastoral record subtype dropdown.
    static readonly PASTORAL_SUB_TYPE = "select#subtypeId";

    // Selector for the save button.
    static readonly SAVE_BUTTON = "input.submit";

    // Test id for the expires-at date input field.
    static readonly DATA_TEST_EXPIRES_AT_INPUT = "modify-section-general-pastoral-expires-at-input";

    // Selector for the entry tip input field.
    static readonly SELECTOR_ENTRY_TIP = "#entry-tip-input";

    // Selector for the selectized pastoral actions input.
    static readonly SELECTOR_ACTIONS_INPUT = "input#pastoral-actions-selectized";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Navigates to the Add Pastoral Record page
     * @param userId - The id of the user to add the pastoral record to
     */
    async goto(userId: number): Promise<void> {
        await this.page.goto(CreatePastoralRecord.URL.replace("{studentId}", userId.toString()));
    }

    /**
     * Navigates to the Edit Pastoral Record page
     * @param userId - The id of the user to add the pastoral record to
     * @param recordId - The id of the record to edit
     */
    async gotoExisting(userId: number, recordId: number): Promise<void> {
        await this.page.goto(
            CreatePastoralRecord.URL_EXISTING.replace("{studentId}", userId.toString()).replace(
                "{recordId}",
                recordId.toString(),
            ),
        );
    }

    /**
     * type a title for the pastoral record
     * @param pastoralTitle - The title of the pastoral record
     */
    async typeTitle(pastoralTitle: string): Promise<void> {
        await this.page.fill(CreatePastoralRecord.PASTORAL_TITLE, pastoralTitle);
    }

    /**
     * click type dropdown
     */
    async clickTypeDropdown(): Promise<void> {
        await this.page.locator(CreatePastoralRecord.PASTORAL_TYPE).click();
    }

    /**
     * click subType dropdown
     */
    async clickSubTypeDropdown(): Promise<void> {
        await this.page.locator(CreatePastoralRecord.PASTORAL_SUB_TYPE).click();
    }

    /**
     * click one of the types by passing its int value: 1 for Academic, 2 for Attendance, 3 for Medical, 4 for Bullying, 5 for Discipline
     * @param type - The type of the pastoral record
     */
    async selectType(type: string): Promise<void> {
        await this.page.locator(CreatePastoralRecord.PASTORAL_TYPE).selectOption({ value: type });
    }

    /**
     * click one of the subtype by passing its int value: if type Academic then subtypes 1 for Award/Achievement, 2 for Learning Difficulties etc
     * @param subType - The sub type of the pastoral record
     */
    async selectSubType(subType: string): Promise<void> {
        await this.page.locator(CreatePastoralRecord.PASTORAL_SUB_TYPE).selectOption({ value: subType });
    }

    /**
     * click record visibility links by passing its title: Role, User, Pastoral Group
     * @param visibilityTitle - The title of the visibility link
     */
    async clickVisibilityLink(visibilityTitle: string): Promise<void> {
        await this.page.locator(`a[title="Add ${visibilityTitle}"]`).click();
    }

    /**
     * click a pastoral role in the dialog: Administration, Teacher, Parent
     * @param role  - The pastoral role for the pastoral record
     */
    async clickPastoralRole(role: string): Promise<void> {
        await this.page.locator(`li[data-role-name="${role}"]`).click();
    }

    /**
     * Set the expires at input to a certain value
     * @param value - The value of the expires at input
     */
    async setExpiresAt(value: string): Promise<void> {
        const input = this.page.getByTestId(CreatePastoralRecord.DATA_TEST_EXPIRES_AT_INPUT);
        await input.fill(value);
        // trigger the change event as that's how our code detects if there's been a manual change to the input
        // see library/Schoolbox/Pastoral/templates/modify_scripts_add_record.twig - $("#expiresAt").on("change", function () {
        await input.evaluate((element) => {
            element.dispatchEvent(new Event("change", { bubbles: true }));
        });
    }

    /**
     * Expects the expires at input to be a certain value
     * @param value - The value of the expires at input
     */
    async expectExpiresAtToBe(value: string): Promise<void> {
        await expect(this.page.getByTestId(CreatePastoralRecord.DATA_TEST_EXPIRES_AT_INPUT)).toHaveValue(value);
    }

    /**
     * click Save button
     */
    async clickSave(): Promise<void> {
        await this.page.locator(CreatePastoralRecord.SAVE_BUTTON).last().click();
    }

    /**
     * check the entry tip is presented with the proper text
     * @param tip - The tip text to check
     */
    async verifyEntryTip(tip: string): Promise<void> {
        await expect(this.page.locator(CreatePastoralRecord.SELECTOR_ENTRY_TIP)).toBeVisible();
        await expect(this.page.locator(CreatePastoralRecord.SELECTOR_ENTRY_TIP)).toContainText(tip);
    }

    /**
     * Check which pastoral actions are currently selected in the form
     * @param expectedTexts - The action names that should be selected
     * @param notExpectedTexts - The action names that should not be selected
     */
    async expectSelectedActions(expectedTexts: string[], notExpectedTexts: string[] = []): Promise<void> {
        const container = this.page
            .locator(CreatePastoralRecord.SELECTOR_ACTIONS_INPUT)
            .locator("xpath=ancestor::div[contains(@class,'selectize-input')]");
        for (const expectedText of expectedTexts) {
            await expect(container).toContainText(expectedText);
        }
        for (const notExpectedText of notExpectedTexts) {
            await expect(container).not.toContainText(notExpectedText);
        }
    }

    /**
     * go to the pastoral create page and creates a pastoral record with minimum required fields
     * subtype is optional
     * @param userId - The id of the user to add the pastoral record to
     * @param title - The title of the pastoral record
     * @param type - The type of the pastoral record
     * @param subType - The sub type of the pastoral record
     */
    async createPastoralRecord(userId: number, title: string, type: string, subType?: string): Promise<void> {
        await this.goto(userId);
        await this.typeTitle(title);
        await this.selectType(type);
        if (subType) {
            await this.selectSubType(subType);
        }
        await this.clickSave();
    }
}
