import { Page } from "@playwright/test";

export default class ImportCourseIntoClass {
    private page: Page;

    static readonly SELECTOR_CLASS_NAME = "input.search-input";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Types a class name
     * @param className - The class name to type
     */
    async typeClassName(className: string): Promise<void> {
        await this.page.fill(ImportCourseIntoClass.SELECTOR_CLASS_NAME, className);
    }

    /**
     * Clicks the class name
     */
    async clickClassName(): Promise<void> {
        await this.page.click("button.search-result");
    }

    /**
     * Ticks a unit checkbox
     * @param checkboxNumber - The checkbox number to tick
     */
    async tickUnitCheckbox(checkboxNumber: number): Promise<void> {
        await this.page.click(`label.checkbox-label[for=import-${checkboxNumber}]`);
    }

    /**
     * Fills an open date
     * @param openDateIdNumber - The open date ID number to fill
     * @param openDate - The open date to fill
     */
    async fillOpenDate(openDateIdNumber: number, openDate: Date): Promise<void> {
        const formattedDate = `${openDate.getDate()}/${openDate.getMonth() + 1}/${openDate.getFullYear()}`;
        await this.page.fill(`input#open_date-${openDateIdNumber}`, formattedDate);
    }

    /**
     * Inserts a due date
     * @param dueDateIdNumber - The due date ID number to insert
     * @param dueDate - The due date to insert
     */
    async insertDueDate(dueDateIdNumber: number, dueDate: Date): Promise<void> {
        const formattedDate = `${dueDate.getDate()}/${dueDate.getMonth() + 1}/${dueDate.getFullYear()}`;
        await this.page.fill(`input#due_date-${dueDateIdNumber}`, formattedDate);
    }

    /**
     * Ticks an assessment checkbox
     * @param checkboxNumber - The checkbox number to tick
     */
    async tickAssessmentCheckbox(checkboxNumber: number): Promise<void> {
        await this.page.click(`label.checkbox-label[for=a_import-${checkboxNumber}]`);
    }

    /**
     * Clicks the submit button
     * @param buttonValue - The button value to click
     */
    async clickSubmit(buttonValue: string): Promise<void> {
        await this.page.click(`input[type=submit][value=${buttonValue}]`);
    }
}
