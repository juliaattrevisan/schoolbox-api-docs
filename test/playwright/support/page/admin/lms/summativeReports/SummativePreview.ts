import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";

export default class SummativeReportList {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly DATA_TEST_PREVIEW_TITLE = "summative-preview-title";

    static readonly DATA_TEST_PREVIEW_SCHOOL_NAME = "summative-preview-school-name";

    static readonly DATA_TEST_PREVIEW_STUDENT_NAME = "summative-preview-student-name";

    static readonly DATA_TEST_PREVIEW_STUDENT_YEAR = "summative-preview-student-year";

    static readonly DATA_TEST_PREVIEW_INTRODUCTION = "summative-preview-introduction";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * check the preview report page shows the details of the summative report: title, school name, student name, year, introduction, school logo
     * @param details - The details to check
     * @param details.title - The title of the summative report
     * @param details.schoolName - The school name
     * @param details.studentName - The student name
     * @param details.year - The student year
     * @param details.introduction - The introduction text
     * @param details.schoolLogo - The school logo
     */
    async checkPreviewReportDetails(details: {
        title?: string;
        schoolName?: string;
        studentName?: string;
        year?: string;
        introduction?: string;
        schoolLogo?: string;
    }): Promise<void> {
        if (details.title) {
            await expect(this.page.getByTestId(SummativeReportList.DATA_TEST_PREVIEW_TITLE)).toContainText(
                details.title,
            );
        }
        if (details.schoolName) {
            await expect(this.page.getByTestId(SummativeReportList.DATA_TEST_PREVIEW_SCHOOL_NAME)).toContainText(
                details.schoolName,
            );
        }
        if (details.studentName) {
            await expect(this.page.getByTestId(SummativeReportList.DATA_TEST_PREVIEW_STUDENT_NAME)).toContainText(
                details.studentName,
            );
        }
        if (details.year) {
            await expect(this.page.getByTestId(SummativeReportList.DATA_TEST_PREVIEW_STUDENT_YEAR)).toContainText(
                details.year,
            );
        }
        if (details.introduction) {
            await expect(this.page.getByTestId(SummativeReportList.DATA_TEST_PREVIEW_INTRODUCTION)).toContainText(
                details.introduction,
            );
        }
        if (details.schoolLogo) {
            await expect(this.page.locator(`img[src*="/storage/image.php?hash"]`)).toBeVisible();
        }
        await this.page.close();
    }
}
