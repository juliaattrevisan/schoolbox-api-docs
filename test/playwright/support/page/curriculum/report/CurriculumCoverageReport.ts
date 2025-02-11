/**
 * Utility class for the Curriculum Coverage Report page
 */

import type { Page } from "@playwright/test";

export default class CurriculumCoverageReport {
    private page: Page;

    static readonly URL = "/curriculum/reports/coverage";

    static readonly DATATEST_GENERATE_REPORT_BUTTON = "cc-input-button";

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
     * Clicks the generate report button
     */
    async submit(): Promise<void> {
        await this.page.getByTestId(CurriculumCoverageReport.DATATEST_GENERATE_REPORT_BUTTON).click();
    }
}
