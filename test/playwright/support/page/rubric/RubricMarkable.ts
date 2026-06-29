import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";

/**
 * Page support for the markable rubric grid.
 * Used in the assessment inline grading page,
 * for both self-assessment and instructor grading.
 */
export default class RubricMarkable {
    private page: Page;

    static readonly DATA_TEST_RUBRIC_INDICATOR = "rubric-markable-indicator"; // Append "-[indicatorId]"

    static readonly DATA_TEST_RUBRIC_DESCRIPTOR = "rubric-markable-descriptor"; // Append "-[levelIndex]"

    static readonly DATA_TEST_RUBRIC_FINAL_SUMMARY = "rubric-final-summary";

    static readonly DATA_TEST_RUBRIC_SUM = "rubric-sum";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Selects a given rubric indicator's descriptor at a given level (column).
     * Scopes to the indicator row, then clicks the descriptor's label at that level,
     * which toggles the underlying descriptor checkbox.
     * @param indicatorId - The ID of the indicator
     * @param levelIndex - The zero-based index of the level (column) to select
     */
    async selectRubricIndicatorByLevel(indicatorId: number, levelIndex: number): Promise<void> {
        await this.page
            .getByTestId(`${RubricMarkable.DATA_TEST_RUBRIC_INDICATOR}-${indicatorId}`)
            .getByTestId(`${RubricMarkable.DATA_TEST_RUBRIC_DESCRIPTOR}-${levelIndex}`)
            .click();
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
