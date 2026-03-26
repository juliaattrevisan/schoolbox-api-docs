import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";

export default class SummativePreview {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly DATA_TEST_PREVIEW_TITLE = "summative-preview-title";

    static readonly DATA_TEST_PREVIEW_SCHOOL_NAME = "summative-preview-school-name";

    static readonly DATA_TEST_PREVIEW_STUDENT_NAME = "summative-preview-student-name";

    static readonly DATA_TEST_PREVIEW_STUDENT_YEAR = "summative-preview-student-year";

    static readonly DATA_TEST_PREVIEW_INTRODUCTION = "summative-preview-introduction";

    static readonly DATA_TEST_HEADING = "summative-preview-summary-heading";

    static readonly DATA_TEST_SUMMARY_FOLDER_NAME = "summative-preview-summary-folder-title-{folderName}";

    static readonly DATA_TEST_SUMMARY_CLASS_DISTRIBUTION_TITLE = "summative-preview-summary-class-distribution";

    static readonly DATA_TEST_SUMMARY_DISTRIBUTION_GRAPH_CONTAINER = "summative-preview-summary-graph-container";

    static readonly DATA_TEST_SUMMARY_RESULTS_TITLE = "summative-preview-summary-results";

    static readonly DATA_TEST_SUMMARY_RESULTS_CONTAINER = "summative-preview-feedback-container-{assessmentName}";

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
            await expect(this.page.getByTestId(SummativePreview.DATA_TEST_PREVIEW_TITLE)).toContainText(details.title);
        }
        if (details.schoolName) {
            await expect(this.page.getByTestId(SummativePreview.DATA_TEST_PREVIEW_SCHOOL_NAME)).toContainText(
                details.schoolName,
            );
        }
        if (details.studentName) {
            await expect(this.page.getByTestId(SummativePreview.DATA_TEST_PREVIEW_STUDENT_NAME)).toContainText(
                details.studentName,
            );
        }
        if (details.year) {
            await expect(this.page.getByTestId(SummativePreview.DATA_TEST_PREVIEW_STUDENT_YEAR)).toContainText(
                details.year,
            );
        }
        if (details.introduction) {
            await expect(this.page.getByTestId(SummativePreview.DATA_TEST_PREVIEW_INTRODUCTION)).toContainText(
                details.introduction,
            );
        }
        if (details.schoolLogo) {
            await expect(this.page.locator(`img[src*="/storage/image.php?hash"]`)).toBeVisible();
        }
        await this.page.close();
    }

    /**
     * check the summary heading is shown in the preview page
     * @param heading - The summary heading to check
     */
    async checkSummaryHeading(heading: string): Promise<void> {
        await expect(this.page.getByTestId(SummativePreview.DATA_TEST_HEADING)).toContainText(heading);
    }

    /**
     * check the summary folder is shown in the preview page
     * @param folderName - The summary folder name to check
     */
    async checkSummaryFolder(folderName: string): Promise<void> {
        const folderTitleLocator = this.page.getByTestId(
            SummativePreview.DATA_TEST_SUMMARY_FOLDER_NAME.replace("{folderName}", folderName),
        );
        await expect(folderTitleLocator).toBeVisible();
        await expect(folderTitleLocator).toContainText(folderName);
    }

    /**
     * check the class distribution title is presented in the preview page
     */
    async checkClassDistributionTitle(): Promise<void> {
        await expect(this.page.getByTestId(SummativePreview.DATA_TEST_SUMMARY_CLASS_DISTRIBUTION_TITLE)).toContainText(
            "class distribution",
        );
    }

    /**
     * check the distribution graph is presented in the preview page
     */
    async checkDistributionGraphIsPresented(): Promise<void> {
        await expect(
            this.page.getByTestId(SummativePreview.DATA_TEST_SUMMARY_DISTRIBUTION_GRAPH_CONTAINER),
        ).toBeVisible();
    }

    /**
     * check the distribution graph is presented for the proper activity type in the preview page
     * @param activityName - The activity name to check the distribution graph for
     */
    async checkDistributionGraphForActivityType(activityName: string): Promise<void> {
        const graphContainer = this.page.getByTestId(SummativePreview.DATA_TEST_SUMMARY_DISTRIBUTION_GRAPH_CONTAINER);
        await expect(graphContainer.locator(`text=${activityName}`)).toBeVisible();
    }

    /**
     * check the Results title is presented in the preview page
     */
    async checkResultsTitle(): Promise<void> {
        await expect(this.page.getByTestId(SummativePreview.DATA_TEST_SUMMARY_RESULTS_TITLE)).toContainText("Results");
    }

    /**
     * check the feedback containers for the specific assessments are presented in the preview page
     * @param assessmentNames - The assessment names to check the feedback containers for
     */
    async checkFeedbackContainersForAssessments(assessmentNames: string[]): Promise<void> {
        for (const assessmentName of assessmentNames) {
            await expect(
                this.page.getByTestId(
                    SummativePreview.DATA_TEST_SUMMARY_RESULTS_CONTAINER.replace("{assessmentName}", assessmentName),
                ),
            ).toBeVisible();
        }
    }

    /**
     * check whether or not the feedback container for the specific assessment contains: grade, comment and/or rubric according to the options provided in the preview page
     * pass true to check the feedback container contains the specific option, pass false to check the feedback container does not contain the specific option
     * @param assessmentName - The assessment name to check the feedback container for
     * @param options - The options to check in the feedback container: grade, comment or rubric
     * @param shouldContain - Whether the feedback container should contain or not contain the specific option
     */
    async checkFeedbackContainerDetails(
        assessmentName: string,
        options: string[],
        shouldContain: boolean,
    ): Promise<void> {
        const feedbackContainer = this.page.getByTestId(
            SummativePreview.DATA_TEST_SUMMARY_RESULTS_CONTAINER.replace("{assessmentName}", assessmentName),
        );
        if (options.includes("grade")) {
            if (shouldContain) {
                await expect(feedbackContainer.locator(`p[class="grade"]`)).toBeVisible();
            } else {
                await expect(feedbackContainer.locator(`p[class="grade"]`)).not.toBeVisible();
            }
        }
        if (options.includes("comment")) {
            if (shouldContain) {
                await expect(feedbackContainer.locator(`div[class="comment"]`)).toBeVisible();
            } else {
                await expect(feedbackContainer.locator(`div[class="comment"]`)).not.toBeVisible();
            }
        }
        if (options.includes("rubric")) {
            if (shouldContain) {
                await expect(feedbackContainer.locator(`div[class="rubric-wrap"]`)).toBeVisible();
            } else {
                await expect(feedbackContainer.locator(`div[class="rubric-wrap"]`)).not.toBeVisible();
            }
        }
    }
}
