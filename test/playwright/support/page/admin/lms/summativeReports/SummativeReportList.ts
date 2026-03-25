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

    static readonly DATA_TEST_REPORT_COMPILING_MESSAGE = "report-generation-in-progress";

    static readonly DATA_TEST_REPORT_COMPILING_MESSAGE_STUDENTS_COUNT = "report-generation-in-progress-students-count";

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
     * click a button to perform action in the summative report list
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
     * @returns The preview page
     */
    async clickPreviewIcon(title: string): Promise<Page> {
        const popupPromise = this.page.waitForEvent("popup");
        const dataTestId = SummativeReportList.DATA_TEST_PREVIEW_ICON.replace("{title}", title);
        await this.page.getByTestId(dataTestId).click();
        const previewPage = await popupPromise;
        return previewPage;
    }

    /**
     * check the report generation in progress message is shown with correct students count
     * @param expectedCount - The expected students count in the message
     */
    async checkReportGenerationInProgressMessage(expectedCount: number): Promise<void> {
        await expect(this.page.getByTestId(SummativeReportList.DATA_TEST_REPORT_COMPILING_MESSAGE)).toBeVisible();
        const expectedMessage = `We are currently working on collating your student reports for ${expectedCount} student${expectedCount > 1 ? "s" : ""} into a zip file.`;
        await expect(
            this.page.getByTestId(SummativeReportList.DATA_TEST_REPORT_COMPILING_MESSAGE_STUDENTS_COUNT),
        ).toContainText(expectedMessage);
    }
}
