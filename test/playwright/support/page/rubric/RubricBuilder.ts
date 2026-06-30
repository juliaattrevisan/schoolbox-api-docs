import type { Locator, Page } from "@playwright/test";

/**
 * Page support for the rubric builder.
 * Used when creating/modifying an assessment.
 */
export default class RubricBuilder {
    private page: Page;

    public static readonly DATA_TEST_RUBRIC_CREATE = "rubric-create";

    public static readonly DATA_TEST_RUBRIC_CREATE_MANUALLY = "rubric-create-manually";

    public static readonly DATA_TEST_RUBRIC_TITLE = "rubric-title";

    public static readonly DATA_TEST_RUBRIC_GRID = "rubric-grid"; // Append "-[rubricId]"

    public static readonly DATA_TEST_RUBRIC_SPAWN_LEVEL = "rubric-spawn-level";

    public static readonly DATA_TEST_RUBRIC_INDICATOR_ROW = "rubric-indicator-row"; // Append "-[capabilityId]-[indicatorId]"

    public static readonly DATA_TEST_RUBRIC_INDICATOR_TOGGLE = "rubric-indicator-toggle";

    public static readonly DATA_TEST_RUBRIC_DESCRIPTOR_CELL = "rubric-descriptor-cell"; // Append "-[descriptorIndex]"

    public static readonly DATA_TEST_RUBRIC_DESCRIPTOR_MAX_VALUE = "rubric-descriptor-max-value";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Clicks the "Add a rubric" button to create a new rubric
     */
    public async clickAddRubric(): Promise<void> {
        await this.page.getByTestId(RubricBuilder.DATA_TEST_RUBRIC_CREATE).click();
    }

    /**
     * Clicks the "Create manually" choice when creating a new rubric
     */
    public async clickCreateRubricManually(): Promise<void> {
        await this.page.getByTestId(RubricBuilder.DATA_TEST_RUBRIC_CREATE_MANUALLY).click();
    }

    /**
     * Sets the (required) title of the rubric being built
     * @param title - The title to set
     */
    public async setRubricTitle(title: string): Promise<void> {
        const titleInput = this.page.getByTestId(RubricBuilder.DATA_TEST_RUBRIC_TITLE);
        await titleInput.fill(title);
        await titleInput.blur(); // commit the value (the input uses v-model.lazy)
    }

    /**
     * Locate a specific rubric grid by its ID
     * @param rubricId - The rubric ID to locate, new unsaved rubrics will have negative IDs starting from -1
     * @returns the locator for the grid which can be used for further narrowing
     */
    public locateGrid(rubricId: number): Locator {
        return this.page.getByTestId(`${RubricBuilder.DATA_TEST_RUBRIC_GRID}-${rubricId}`);
    }

    /**
     * Adds a new rubric indicator level
     */
    public async addRubricLevel(): Promise<void> {
        await this.page.getByTestId(RubricBuilder.DATA_TEST_RUBRIC_SPAWN_LEVEL).click();
    }

    /**
     * Toggle a given rubric indicator
     * @param capabilityId - The capability ID
     * @param indicatorId - The indicator ID
     */
    public async toggleRubricIndicator(capabilityId: number, indicatorId: number): Promise<void> {
        const indicatorToggle = this.page
            .getByTestId(`${RubricBuilder.DATA_TEST_RUBRIC_INDICATOR_ROW}-${capabilityId}-${indicatorId}`)
            .getByTestId(RubricBuilder.DATA_TEST_RUBRIC_INDICATOR_TOGGLE);
        await indicatorToggle.click();
    }

    /**
     * Fill in points for a given descriptor
     * @param capabilityId - The capability ID
     * @param indicatorId - The indicator ID
     * @param descriptorIndex - The index of the descriptor
     * @param value - The value to set
     */
    public async fillRubricCriterionPoints(
        capabilityId: number,
        indicatorId: number,
        descriptorIndex: number,
        value: string | number,
    ): Promise<void> {
        const descriptor = this.page
            .getByTestId(`${RubricBuilder.DATA_TEST_RUBRIC_INDICATOR_ROW}-${capabilityId}-${indicatorId}`)
            .getByTestId(`${RubricBuilder.DATA_TEST_RUBRIC_DESCRIPTOR_CELL}-${descriptorIndex}`);

        const pointsInput = descriptor.getByTestId(RubricBuilder.DATA_TEST_RUBRIC_DESCRIPTOR_MAX_VALUE);
        await pointsInput.fill(value.toString());
    }
}
