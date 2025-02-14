/**
 * Utility class for the Curriculum Coverage Report page
 */

import type { Page } from "@playwright/test";
import { expect } from "@playwright/test";

export default class CurriculumCoverageReport {
    private page: Page;

    static readonly URL = "/curriculum/reports/coverage";

    static readonly DATATEST_GENERATE_REPORT_BUTTON = "cc-input-button";

    static readonly DATATEST_REPORT_LOADING = "cc-report-loading";

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

    /**
     * Expect the loading spinner to be visible
     */
    async expectLoading(): Promise<void> {
        await expect(this.page.getByTestId(CurriculumCoverageReport.DATATEST_REPORT_LOADING)).toBeVisible();
    }
}
