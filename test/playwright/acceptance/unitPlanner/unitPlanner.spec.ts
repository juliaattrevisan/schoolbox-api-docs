import { test, expect } from "@schoolboxTest/playwright/support/integrations";

test.describe("Unit Planner Tests", () => {
    test.beforeEach(async ({ tester, page }) => {
        const teacher = "Jack Coles";
        await tester.impersonate(teacher);
        await page.goto("/course/plan/6");
    });

    // TODO: Split selectors into a UnitPlannerPage support file

    test("Create a Textbox", async ({ tester, page }) => {
        const unitId = 15; // "Romeo and Juliet"
        const overviewColumnId = "overview-1"; // "Unit Overview"
        const overviewColumnLabel = "Unit Overview";

        const textBoxCell = page.getByTestId(`unit-planner-textbox-action-create-${unitId}-${overviewColumnId}`);

        tester.isGoingTo("create a Unit Overview textbox for Romeo and Juliet");
        await textBoxCell.click();
        await page.waitForTimeout(500); // Wait for the textbox to be focused

        tester.isGoingTo("type in the textbox and click save");
        const textToType = "By the end of this unit, you will know everything about Snakes! 🐍";
        await page.keyboard.type(textToType);
        await page.getByTestId(`unit-planner-textbox-action-save-${unitId}-${overviewColumnId}`).click();

        tester.isGoingTo("go to the unit homepage and verify the textbox content");
        const homepageUrl = (await page.getByTestId(`unit-planner-unit-link-${unitId}`).getAttribute("href")) as string;
        expect(homepageUrl).not.toBeNull();
        await page.goto(homepageUrl);

        const componentInstanceId = await page
            .getByTestId("homepage-component-titlebar-name")
            .getByText(overviewColumnLabel)
            .getAttribute("data-test-component-instance-id");
        expect(componentInstanceId).not.toBeNull();

        await expect(page.getByTestId(`component-textbox-${componentInstanceId}`)).toHaveText(textToType);
    });

    test("Resequence Units", async ({ tester, page }) => {
        const romeoUnitId = 15; // "Romeo and Juliet"
        const persuasionUnitId = 17; // "Persuasion and the Media"

        /**
         * Get the current order of units
         * @returns - The order of unit IDs
         */
        async function getUnitOrder(): Promise<string[]> {
            const allUnitRowSelector = /^unit-row-/;
            const pageOrder = await page
                .getByTestId(allUnitRowSelector)
                .evaluateAll((unitRows) => unitRows.map((unitRow) => unitRow.getAttribute("data-test-unit-id")));
            return pageOrder.filter((unitId) => unitId !== null) as string[];
        }

        const initialUnitOrder = await getUnitOrder();

        // Expect Persuasion and the Media to be below Romeo and Juliet
        expect(initialUnitOrder.indexOf(romeoUnitId.toString())).toBeLessThan(
            initialUnitOrder.indexOf(persuasionUnitId.toString()),
        );

        const persuasionUnitRow = page.getByTestId(`unit-row-${persuasionUnitId}`);

        tester.isGoingTo("drag Persuasion and the Media above Romeo and Juliet");
        const persuasionDragHandle = persuasionUnitRow.getByTestId("unit-row-drag-handle");
        await persuasionDragHandle.scrollIntoViewIfNeeded();
        await persuasionDragHandle.hover();
        await page.mouse.down();

        const unitPlannerContainer = page.getByTestId("unit-planner-container");

        // Move mouse to top of container, then wait for the reorder to happen
        await unitPlannerContainer.hover({ position: { x: 0, y: 0 } });

        tester.isGoingTo("keep dragging and wait for the units to reorder");
        let newUnitOrder = await getUnitOrder();
        const startTime = Date.now();
        while (
            newUnitOrder.indexOf(persuasionUnitId.toString()) > newUnitOrder.indexOf(romeoUnitId.toString()) &&
            Date.now() - startTime < 5000 // Timeout after 5 seconds
        ) {
            // eslint-disable-next-line no-await-in-loop -- Need to wait for the order to change, await is acceptable here.
            newUnitOrder = await getUnitOrder();
        }

        await page.mouse.up();

        tester.isGoingTo("verify that the units have been correctly reordered");
        expect(newUnitOrder.indexOf(persuasionUnitId.toString())).toBeLessThan(
            newUnitOrder.indexOf(romeoUnitId.toString()),
        );

        await expect(page.getByTestId("toast")).toHaveAttribute("data-test-is-success", "true");

        await page.reload();

        const finalUnitOrder = await getUnitOrder();
        expect(finalUnitOrder).toEqual(newUnitOrder);
    });

    test.afterEach(async ({ tester }) => {
        await tester.logout();
    });

    // TODO: Use afterAll to reverse any changes we made
});
