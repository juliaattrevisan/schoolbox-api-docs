import type { Page, Locator } from "@playwright/test";

export default class ClassReport {
    private page: Page;

    static readonly URL = "/pastoral/class/{classId}?30=&31=&32=&35[includeActiveRecords]=1";

    static readonly DATATEST_STUDENT_RECORD = "pastoral-class-report-student-{studentId}";

    static readonly DATATEST_STATUS_FILTER = "sbx-pop-out-filter-component-35-button";

    static readonly DATATEST_STATUS_FILTER_ARCHIVED_CHECKBOX = "selectionCheckbox-35-includeArchivedRecords";

    static readonly DATATEST_APPLY_FILTERS_BUTTON = "panel-apply-filters-button";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Navigates to the profile page
     * @param id - The user id
     */
    async goto(id: number): Promise<void> {
        await this.page.goto(ClassReport.URL.replace("{classId}", id.toString()));
    }

    /**
     * Returns the students record set
     * @param studentId - The student id
     * @returns - The locator for the student record
     */
    getStudentRecord(studentId: number): Locator {
        return this.page.getByTestId(ClassReport.DATATEST_STUDENT_RECORD.replace("{studentId}", studentId.toString()));
    }

    /**
     * Clicks the status filter
     */
    async clickStatusFilter(): Promise<void> {
        await this.page.getByTestId(ClassReport.DATATEST_STATUS_FILTER).click();
    }

    /**
     * Clicks the archived checkbox within the status filter
     */
    async clickArchivedCheckbox(): Promise<void> {
        await this.page.getByTestId(ClassReport.DATATEST_STATUS_FILTER_ARCHIVED_CHECKBOX).click();
    }

    /**
     * Clicks the apply filters button
     */
    async clickApplyFiltersButton(): Promise<void> {
        await this.page.getByTestId(ClassReport.DATATEST_APPLY_FILTERS_BUTTON).click();
    }
}
