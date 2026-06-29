import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";

/**
 * Page object for the markable rubric grid (used in the assessment inline grading
 * page, for both self-assessment and instructor grading).
 */
export default class RubricMarkable {
    private page: Page;

    static readonly DATA_TEST_RUBRIC_DESCRIPTOR_INPUT = "rubric-descriptor-input"; // append "-[descriptorId]-[value]"

    static readonly DATA_TEST_RUBRIC_FINAL_SUMMARY = "rubric-final-summary";

    static readonly DATA_TEST_RUBRIC_SUM = "rubric-sum";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Selects a given rubric indicator level based on its value in the feedback form
     * @param indicatorId - The ID of the indicator
     * @param value - The value of the indicator
     */
    async selectRubricIndicatorByValue(indicatorId: number, value: string | number): Promise<void> {
        // TODO: refine this to match descriptor/indicator correctly
        const inputId = await this.page
            .getByTestId(`${RubricMarkable.DATA_TEST_RUBRIC_DESCRIPTOR_INPUT}-${indicatorId}-${value}`)
            .getAttribute("id");
        await this.page.locator(`label[for="${inputId}"]`).click();
    }

    /**
     * Ensure the value of the rubric final summary bar matches the expected value
     * @param value - The expected value
     */
    async expectRubricFinalSummaryMark(value: string): Promise<void> {
        const finalSummaryBar = this.page.getByTestId(RubricMarkable.DATA_TEST_RUBRIC_FINAL_SUMMARY);
        await expect(finalSummaryBar).toHaveText(value);
    }

    /**
     * Expect that no rubric final summary bar is present
     */
    async expectNoRubricFinalSummary(): Promise<void> {
        const finalSummaryBar = this.page.getByTestId(RubricMarkable.DATA_TEST_RUBRIC_FINAL_SUMMARY);
        await expect(finalSummaryBar).not.toBeVisible();
    }

    /**
     * Expect the rubric "Assign XX from Rubric" button to contain the expected sum (value of XX)
     * @param value - The expected value
     */
    async expectRubricSum(value: string): Promise<void> {
        const rubricSum = this.page.getByTestId(RubricMarkable.DATA_TEST_RUBRIC_SUM);
        await expect(rubricSum).toHaveText(value);
    }

    /**
     * Expect that no rubric sum is present
     */
    async expectNoRubricSum(): Promise<void> {
        const rubricSum = this.page.getByTestId(RubricMarkable.DATA_TEST_RUBRIC_SUM);
        await expect(rubricSum).not.toBeVisible();
    }
}
