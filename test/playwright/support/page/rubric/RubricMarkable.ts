import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";

/**
 * Page support for the markable rubric grid.
 * Used in the assessment inline grading page,
 * for both self-assessment and instructor grading.
 */
export default class RubricMarkable {
    private page: Page;

    public static readonly DATA_TEST_RUBRIC_INDICATOR = "rubric-markable-indicator"; // Append "-[indicatorId]"

    public static readonly DATA_TEST_RUBRIC_DESCRIPTOR = "rubric-markable-descriptor"; // Append "-[levelIndex]"

    public static readonly DATA_TEST_RUBRIC_FINAL_SUMMARY = "rubric-final-summary";

    public static readonly DATA_TEST_RUBRIC_SUM = "rubric-sum";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Select a descriptor for a particular rubric indicator based on the associated level.
     * @param indicatorId - The ID of the indicator
     * @param levelIndex - The zero-based index of the target descriptor's level (column)
     */
    public async selectRubricDescriptorByLevel(indicatorId: number, levelIndex: number): Promise<void> {
        await this.page
            .getByTestId(`${RubricMarkable.DATA_TEST_RUBRIC_INDICATOR}-${indicatorId}`)
            .getByTestId(`${RubricMarkable.DATA_TEST_RUBRIC_DESCRIPTOR}-${levelIndex}`)
            .click();
    }

    /**
     * Ensure the value of the rubric final summary bar matches the expected value
     * @param value - The expected value
     */
    public async expectRubricFinalSummaryMark(value: string): Promise<void> {
        const finalSummaryBar = this.page.getByTestId(RubricMarkable.DATA_TEST_RUBRIC_FINAL_SUMMARY);
        await expect(finalSummaryBar).toHaveText(value);
    }

    /**
     * Expect that no rubric final summary bar is present
     */
    public async expectNoRubricFinalSummary(): Promise<void> {
        const finalSummaryBar = this.page.getByTestId(RubricMarkable.DATA_TEST_RUBRIC_FINAL_SUMMARY);
        await expect(finalSummaryBar).not.toBeVisible();
    }

    /**
     * Expect the rubric "Assign XX from Rubric" button to contain the expected sum (value of XX)
     * @param value - The expected value
     */
    public async expectRubricSum(value: string): Promise<void> {
        const rubricSum = this.page.getByTestId(RubricMarkable.DATA_TEST_RUBRIC_SUM);
        await expect(rubricSum).toHaveText(value);
    }

    /**
     * Expect that no rubric sum is present
     */
    public async expectNoRubricSum(): Promise<void> {
        const rubricSum = this.page.getByTestId(RubricMarkable.DATA_TEST_RUBRIC_SUM);
        await expect(rubricSum).not.toBeVisible();
    }
}
