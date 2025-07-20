/**JsonStream.php
 * Utility class for the Curriculum Coverage Report page
 */

import type { Page, Locator } from "@playwright/test";
import { expect } from "@playwright/test";

export default class CurriculumCoverageReport {
    private page: Page;

    static readonly URL = "/curriculum/reports/coverage";

    static readonly URL_DISABLED = "/curriculum/reports/coverage?courseId=4&nodeId=247%2C1"; // url used to check the disabled toggle

    static readonly URL_COURSE = "/curriculum/reports/coverage?courseId={courseId}";

    static readonly URL_COURSE_CURRICULUM = "/curriculum/reports/coverage?courseId={courseId}&nodeId={curriculumId}";

    static readonly DATA_TEST_GENERATE_REPORT_BUTTON = "cc-input-button";

    static readonly DATA_TEST_REPORT_LOADING = "cc-report-loading";

    static readonly DATA_TEST_TABLE = "cc-report-table";

    static readonly DATA_TEST_TABLE_NODE_EXPAND_BUTTON = "cc-report-table-node-expand-button-";

    static readonly DATA_TEST_FILTER_COURSE = "cc-report-filters-label-course";

    static readonly DATA_TEST_FILTER_UNIT = "cc-report-filters-label-unit";

    static readonly DATA_TEST_FILTER_ACTIVITY = "cc-report-filters-label-activity";

    static readonly DATA_TEST_ACELA1553_ROMEO = "cc-report-table-node-total-pop-out-button-6-88-149"; // ACELA1553 / Romeo & Juliet table cell

    static readonly DATA_TEST_DISABLED_TOGGLE = "cc-report-filters-disabled-toggle";

    static readonly SELECTOR_YEAR_9_TOTAL = "table tbody tr:nth-child(4) td:nth-child(2)"; // the Year 9 Curriculum total usage table cell at its default state

    static readonly SELECTOR_YEAR_4_TOTAL = "table tbody tr:nth-child(4) td:nth-child(2)"; // the Year 4 Curriculum total usage table cell at its default state (on the URL DISABLED)

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Navigates to the curriculum coverage report page
     */
    async goto(): Promise<void> {
        await this.page.goto(CurriculumCoverageReport.URL);
    }

    /**
     * Navigates to the curriculum coverage report page for a specific course
     * @param courseId - The id of the course to navigate to
     */
    async gotoReportCourse(courseId: number): Promise<void> {
        await this.page.goto(CurriculumCoverageReport.URL_COURSE.replace("{courseId}", courseId.toString()));
    }

    /**
     * Navigates to the curriculum coverage report page for a specific course and curriculum node
     * @param courseId - The id of the course to navigate to
     * @param curriculumId  - The id of the curriculum node to navigate to
     */
    async gotoReportCourseCurriculum(courseId: number, curriculumId: number): Promise<void> {
        await this.page.goto(
            CurriculumCoverageReport.URL_COURSE_CURRICULUM.replace("{courseId}", courseId.toString()).replace(
                "{curriculumId}",
                curriculumId.toString(),
            ),
        );
    }

    /**
     * Navigates to the curriculum coverage report page with the disabled toggle test
     */
    async gotoDisabledTest(): Promise<void> {
        await this.page.goto(CurriculumCoverageReport.URL_DISABLED);
    }

    /**
     * Clicks the generate report button
     */
    async submit(): Promise<void> {
        await this.page.getByTestId(CurriculumCoverageReport.DATA_TEST_GENERATE_REPORT_BUTTON).click();
    }

    /**
     * Expect the loading spinner to be visible
     */
    async expectLoading(): Promise<void> {
        await expect(this.page.getByTestId(CurriculumCoverageReport.DATA_TEST_REPORT_LOADING)).toBeVisible();
    }

    /**
     * Returns the locator for the report table
     * @returns - The locator for the report table
     */
    getTable(): Locator {
        return this.page.getByTestId(CurriculumCoverageReport.DATA_TEST_TABLE);
    }

    /**
     * To navigate to nodes in the curriculum tree
     * @param ids - The ids of the nodes to navigate to
     */
    async navigateTree(ids: number[]): Promise<void> {
        // eslint-disable-next-line no-restricted-syntax -- need to loop through each sequentially, rather than in parallel with Promise.all
        for (const id of ids) {
            // eslint-disable-next-line no-await-in-loop -- need to click each item sequentially
            await this.page
                .getByTestId(`${CurriculumCoverageReport.DATA_TEST_TABLE_NODE_EXPAND_BUTTON}${id.toString()}`)
                .click();
        }
    }

    /**
     * Clicks the course filter
     */
    async clickCourseFilter(): Promise<void> {
        await this.page.getByTestId(CurriculumCoverageReport.DATA_TEST_FILTER_COURSE).click();
    }

    /**
     * Clicks the unit filter
     */
    async clickUnitFilter(): Promise<void> {
        await this.page.getByTestId(CurriculumCoverageReport.DATA_TEST_FILTER_UNIT).click();
    }

    /**
     * Clicks the activity filter
     */
    async clickActivityFilter(): Promise<void> {
        await this.page.getByTestId(CurriculumCoverageReport.DATA_TEST_FILTER_ACTIVITY).click();
    }

    /**
     * Clicks the show disabled toggle
     */
    async clickShowDisabledToggle(): Promise<void> {
        await this.page.getByTestId(CurriculumCoverageReport.DATA_TEST_DISABLED_TOGGLE).click();
    }

    /**
     * Returns the locator for the Year 9 Curriculum total usage table cell
     * @returns - The locator for the Year 9 Curriculum total usage table cell
     */
    getYear9TotalUsageTableCell(): Locator {
        return this.page.locator(CurriculumCoverageReport.SELECTOR_YEAR_9_TOTAL);
    }

    /**
     * Returns the locator for the Year 4Curriculum total usage table cell
     * @returns - The locator for the Year 4 Curriculum total usage table cell
     */
    getYear4TotalUsageTableCell(): Locator {
        return this.page.locator(CurriculumCoverageReport.SELECTOR_YEAR_4_TOTAL);
    }

    /**
     * Returns the locator for the ACELA1553 / Romeo & Juliet table cell
     * @returns - The locator for the ACELA1553 / Romeo & Juliet table cell
     */
    acela1553RomeoTableCell(): Locator {
        return this.page.getByTestId(CurriculumCoverageReport.DATA_TEST_ACELA1553_ROMEO);
    }
}
