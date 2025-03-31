import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import UnitPlanner from "@schoolboxTest/playwright/support/page/unitPlanner/UnitPlanner";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";

test.describe("Unit Planner Tests", () => {
    test.describe.configure({ mode: "serial" });

    let unitPlanner: UnitPlanner;
    const unitId = 15; // "Romeo and Juliet"
    const overviewColumnId = "overview-1"; // "Unit Overview"
    const overviewColumnLabel = "Unit Overview";
    const romeoUnitId = 15; // "Romeo and Juliet"
    const persuasionUnitId = 17; // "Persuasion and the Media"

    test.beforeEach(async ({ tester, page }) => {
        const teacher = "Jack Coles";
        unitPlanner = new UnitPlanner(tester, page);
        await tester.impersonate(teacher);
        await unitPlanner.goto(6);
    });

    // TODO: Split selectors into a UnitPlannerPage support file

    test("Create a Textbox", async ({ tester, page }) => {
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
        /**
         * Get the current order of units
         * @returns - The order of unit IDs
         */
        async function getUnitOrder(): Promise<string[]> {
            const allUnitRowSelector = /^unit-row-/;
            const pageOrder = await page
                .getByTestId(allUnitRowSelector)
                .evaluateAll((unitRows) => unitRows.map((unitRow) => unitRow.getAttribute("data-test-unit-id")));
            return pageOrder.filter((id) => id !== null);
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

    test("Create, modifying and deleting learning activities", async ({ tester, page, db }) => {
        const unitplanner = new UnitPlanner(tester, page);
        const assessmentForm = new AssessmentForm(tester, page);

        tester.isGoingTo("check all options are presented under Create Activity menu");
        await unitplanner.clickCreateActivityButton(persuasionUnitId);
        await unitPlanner.checkCreateActivityOptions("project", persuasionUnitId);
        await unitPlanner.checkCreateActivityOptions("due-work", persuasionUnitId);
        await unitPlanner.checkCreateActivityOptions("quiz", persuasionUnitId);
        await unitPlanner.checkCreateActivityOptions("task", persuasionUnitId);
        await unitPlanner.checkCreateActivityOptions("lesson-plan", persuasionUnitId);

        tester.isGoingTo("create a due work for Persuasion and Media unit via unit planner");
        await unitplanner.clickCreateActivityOption("due-work", persuasionUnitId);

        tester.isGoingTo("Fill out a due work assessment form and submit it");
        await assessmentForm.setTitle("Test Due Activity via unit planner");
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
        await assessmentForm.clickCreate();
        tester.isGoingTo("checking the activity is presented in the learning activities list in the unit planner");
        await expect(page.getByText("Test Due Activity via unit planner")).toBeVisible();

        const assessmentId = (await db.grabFromDatabase("assessment", "id", {
            deleted_at: null,
            "title like": "Test Due Activity via unit planner",
        })) as number;

        tester.isGoingTo("check all options are presented under the activiity menu");
        await unitplanner.clickActivityDotMenu(assessmentId);
        await unitplanner.checkActivityMenuOptions(assessmentId);

        tester.isGoingTo("Modify a due work activity via unit planner");
        await unitplanner.clickActivityModify(assessmentId);
        await assessmentForm.setTitle("Updated via unit planner");
        await assessmentForm.update();
        tester.isGoingTo(
            "checking the activity is presented updated in the learning activities list in the unit planner",
        );
        await expect(page.getByTestId("unit-planner-textbox-action-create-17-overview-1")).toBeVisible();
        await expect(page.getByText("Updated via unit planner")).toBeVisible();

        tester.isGoingTo("Delete the due work assessment via unit planner");
        await unitplanner.clickActivityDotMenu(assessmentId);
        await unitplanner.clickActivityDelete(assessmentId);
        tester.isGoingTo("checking the activity is not presented in the learning activities list in the unit planner");
        await expect(page.getByText("Test Due Activity via unit planner")).not.toBeVisible();
        await expect(page.getByText("Updated via unit planner")).not.toBeVisible();
    });

    test.afterEach(async ({ tester }) => {
        await tester.logout();
    });

    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("find the textbox that was created in this test so it can be deleted");

        const romeoUnitFolderId = (await db.grabFromDatabase("course_unit_specification", "folder_id", {
            id: romeoUnitId,
        })) as number | null;
        expect(romeoUnitFolderId).not.toBeNull();

        const persuasionUnitFolderId = (await db.grabFromDatabase("course_unit_specification", "folder_id", {
            id: persuasionUnitId,
        })) as number | null;
        expect(romeoUnitFolderId).not.toBeNull();

        const textBoxID = (await db.grabFromDatabase("textBox", "textBoxID", {
            categoryID: romeoUnitFolderId,
            heading: overviewColumnLabel,
        })) as number | null;

        if (textBoxID !== null) {
            const textboxComponentDefinitionId = (await db.grabFromDatabase("component_definition", "id", {
                class: "Schoolbox\\Resource\\Textbox\\Component\\Homepage\\Controller",
            })) as number | null;
            expect(textboxComponentDefinitionId).not.toBeNull();

            const textboxComponentInstanceId = (await db.grabFromDatabase("component_instance", "id", {
                module: "homepage",
                identifier: romeoUnitFolderId,
                component_definition_id: textboxComponentDefinitionId,
            })) as number | null;

            if (textboxComponentInstanceId === null) {
                baseTester.isGoingTo("skip deletion as the textbox component instance was not found");
                return;
            }

            baseTester.isGoingTo(`delete the textbox with ID ${textBoxID}`);
            await db.dontHaveInDatabase("textBox", {
                textBoxID,
            });

            baseTester.isGoingTo(`delete the component instance with ID ${textboxComponentInstanceId}`);
            await db.dontHaveInDatabase("component_config_instance", {
                component_instance_id: textboxComponentInstanceId,
            });

            await db.dontHaveInDatabase("component_instance", {
                id: textboxComponentInstanceId,
            });
        }

        baseTester.isGoingTo("update the unit order back to the original order");
        await db.updateInDatabase("folder", { sequence: 3 }, { id: romeoUnitFolderId });
        await db.updateInDatabase("folder", { sequence: 2 }, { id: persuasionUnitFolderId });

        baseTester.isGoingTo("delete the due work assessment created in this test if it exists");
        await db.updateInDatabase(
            "assessment",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Test Due Activity via unit planner" },
        );
        await db.updateInDatabase(
            "assessment",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Updated via unit planner" },
        );
    });
});
