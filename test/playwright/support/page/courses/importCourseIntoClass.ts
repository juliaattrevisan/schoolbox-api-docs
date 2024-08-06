import { Page } from "@playwright/test";

export class ImportCourseIntoClass {
    private page: Page;

    static readonly SELECTOR_CLASS_NAME = "input.search-input";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Types a class name
     * @param { string } className - The class name to type
     */
    async typeClassName(className: string) {
        await this.page.fill(ImportCourseIntoClass.SELECTOR_CLASS_NAME, className);
    }

    /**
     * Clicks the class name
     */
    async clickClassName() {
        await this.page.click("button.search-result");
    }

    /**
     * Ticks a unit checkbox
     * @param { number } checkboxNumber - The checkbox number to tick
     */
    async tickUnitCheckbox(checkboxNumber: number) {
        await this.page.click(`label.checkbox-label[for=import-${checkboxNumber}]`);
    }

    /**
     * Fills an open date
     * @param { number } openDateIdNumber - The open date ID number to fill
     * @param { Date } openDate - The open date to fill
     */
    async fillOpenDate(openDateIdNumber: number, openDate: Date) {
        const formattedDate = `${openDate.getDate()}/${openDate.getMonth() + 1}/${openDate.getFullYear()}`;
        await this.page.fill(`input#open_date-${openDateIdNumber}`, formattedDate);
    }

    /**
     * Inserts a due date
     * @param { number } dueDateIdNumber - The due date ID number to insert
     * @param { Date } dueDate - The due date to insert
     */
    async insertDueDate(dueDateIdNumber: number, dueDate: Date) {
        const formattedDate = `${dueDate.getDate()}/${dueDate.getMonth() + 1}/${dueDate.getFullYear()}`;
        await this.page.fill(`input#due_date-${dueDateIdNumber}`, formattedDate);
    }

    /**
     * Ticks an assessment checkbox
     * @param { number } checkboxNumber - The checkbox number to tick
     */
    async tickAssessmentCheckbox(checkboxNumber: number) {
        await this.page.click(`label.checkbox-label[for=a_import-${checkboxNumber}]`);
    }

    /**
     * Clicks the submit button
     * @param { string } buttonValue - The button value to click
     */
    async clickSubmit(buttonValue: string) {
        await this.page.click(`input[type=submit][value=${buttonValue}]`);
    }
}
