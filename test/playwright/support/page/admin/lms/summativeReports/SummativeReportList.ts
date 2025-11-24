import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import Common from "@schoolboxTest/playwright/support/page/Common";

export default class SummativeReportList {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/adminv2/summative-report";

    static readonly EMPTY_STATE = "div.empty-state";

    static readonly BUTTON_CREATE_SUMMATIVE_REPORT = "Click here";

    static readonly DATA_TEST_SUMMATIVE_TITLE = `summative-report-title-{title}`;

    static readonly DATA_TEST_SUMMATIVE_ACTION_BUTTON = `summative-report-{action}-{title}`;

    static readonly DATA_TEST_PREVIEW_ICON = "summative-report-preview-{title}";

    static readonly DATA_TEST_PREVIEW_TITLE = "summative-preview-title";

    static readonly DATA_TEST_PREVIEW_SCHOOL_NAME = "summative-preview-school-name";

    static readonly DATA_TEST_PREVIEW_STUDENT_NAME = "summative-preview-student-name";

    static readonly DATA_TEST_PREVIEW_STUDENT_YEAR = "summative-preview-student-year";

    static readonly DATA_TEST_PREVIEW_INTRODUCTION = "summative-preview-introduction";

    private common: Common;

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
        this.common = new Common(page);
    }

    /**
     * Navigates to the summative report list page
     */
    async goto(): Promise<void> {
        await this.page.goto(SummativeReportList.URL);
    }

    /**
     * check the empty state is presented
     */
    async checkEmptyStateIsPresented(): Promise<void> {
        await expect(this.page.locator(SummativeReportList.EMPTY_STATE)).toBeVisible();
        await expect(this.page.locator(SummativeReportList.EMPTY_STATE)).toContainText(
            "No summative reports have been created.",
        );
        await expect(this.page.locator(SummativeReportList.EMPTY_STATE)).toContainText("to build a summative report.");
    }

    /**
     * click the create summative report button
     */
    async clickCreateSummativeReportButton(): Promise<void> {
        await this.page.getByText(SummativeReportList.BUTTON_CREATE_SUMMATIVE_REPORT).click();
    }

    /**
     * check summative report title is presented in the list
     * @param title - The title of the summative report
     */
    async checkSummativeReportTitleIsPresented(title: string): Promise<void> {
        const dataTestId = SummativeReportList.DATA_TEST_SUMMATIVE_TITLE.replace("{title}", title);
        await expect(this.page.getByTestId(dataTestId)).toContainText(title);
    }

    /**
     * delete a summative report by its title
     * @param title - The title of the summative report
     */
    async deleteSummativeReportByTitle(title: string): Promise<void> {
        await this.clickSummativeReportActionButton("delete", title);
        await this.common.clickConfirmButton();
        await expect(this.page.getByTestId("toast")).toBeHidden();
    }

    /**
     * check the summative report is not presented in the list
     * @param title - The title of the summative report
     */
    async checkSummativeReportIsDeleted(title: string): Promise<void> {
        const dataTestId = SummativeReportList.DATA_TEST_SUMMATIVE_TITLE.replace("{title}", title);
        await expect(this.page.getByTestId(dataTestId)).toHaveCount(0);
    }

    /**
     * click a buttons to action in the summative report list
     * @param action - The action to perform (preview, download, modify, copy, delete)
     * @param title - The title of the summative report
     */
    async clickSummativeReportActionButton(
        action: "preview" | "download" | "modify" | "copy" | "delete",
        title: string,
    ): Promise<void> {
        const dataTestId = SummativeReportList.DATA_TEST_SUMMATIVE_ACTION_BUTTON.replace("{action}", action).replace(
            "{title}",
            title,
        );
        await this.page.getByTestId(dataTestId).click();
    }

    /**
     * click the preview icon of a summative report
     * @param title - The title of the summative report
     */
    async clickPreviewIcon(title: string): Promise<void> {
        const dataTestId = SummativeReportList.DATA_TEST_PREVIEW_ICON.replace("{title}", title);
        await this.page.getByTestId(dataTestId).click();
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
        const [newPage] = await Promise.all([
            this.page.context().waitForEvent("page"), // Wait for new page to open
        ]);
        if (details.title) {
            await expect(newPage.getByTestId(SummativeReportList.DATA_TEST_PREVIEW_TITLE)).toContainText(details.title);
        }
        if (details.schoolName) {
            await expect(newPage.getByTestId(SummativeReportList.DATA_TEST_PREVIEW_SCHOOL_NAME)).toContainText(
                details.schoolName,
            );
        }
        if (details.studentName) {
            await expect(newPage.getByTestId(SummativeReportList.DATA_TEST_PREVIEW_STUDENT_NAME)).toContainText(
                details.studentName,
            );
        }
        if (details.year) {
            await expect(newPage.getByTestId(SummativeReportList.DATA_TEST_PREVIEW_STUDENT_YEAR)).toContainText(
                details.year,
            );
        }
        if (details.introduction) {
            await expect(newPage.getByTestId(SummativeReportList.DATA_TEST_PREVIEW_INTRODUCTION)).toContainText(
                details.introduction,
            );
        }
        if (details.schoolLogo) {
            await expect(newPage.locator(`img[src*="/storage/image.php?hash"]`)).toBeVisible();
        }
        await newPage.close();
    }
}
