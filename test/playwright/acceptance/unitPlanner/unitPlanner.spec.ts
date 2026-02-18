/**
 * Test some of the key functionality of the unit planner page
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test unitPlanner.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import UnitPlanner from "@schoolboxTest/playwright/support/page/unitPlanner/UnitPlanner";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import CurriculumSelector from "@schoolboxTest/playwright/support/page/curriculum/selector/CurriculumSelector";

test.describe("Unit Planner Tests", () => {
    test.describe.configure({ mode: "serial" });

    let unitPlanner: UnitPlanner;
    const unitId = 15; // "Romeo and Juliet"
    const overviewColumnLabel = "Unit Overview";
    const romeoUnitId = 15; // "Romeo and Juliet"
    const persuasionUnitId = 17; // "Persuasion and the Media"
    const toKillMockingbirdId = 16; // "To Kill a Mockingbird"
    const folderId = 89; // "To Kill a Mockingbird" folder ID

    test.beforeEach(async ({ tester, page }) => {
        const teacher = "Jack Coles";
        unitPlanner = new UnitPlanner(tester, page);
        await tester.loginAsAdmin();
        await tester.impersonate(teacher);
        await unitPlanner.goto(6);
    });

    test("Create a Textbox", async ({ tester, page }) => {
        tester.isGoingTo("create a Unit Overview textbox for Romeo and Juliet");
        await unitPlanner.clickCreateTextBoxButton(unitId);

        tester.isGoingTo("type in the textbox and click save");
        const textToType = "By the end of this unit, you will know everything about Snakes! 🐍";
        await page.keyboard.type(textToType);
        await unitPlanner.clickTextBoxSaveButton(unitId);

        tester.isGoingTo("go to the unit homepage and verify the textbox content");
        await unitPlanner.clickUnitLink(unitId);

        const componentInstanceId = await page
            .getByTestId("homepage-component-titlebar-name")
            .getByText(overviewColumnLabel)
            .getAttribute("data-test-component-instance-id");
        expect(componentInstanceId).not.toBeNull();

        await expect(page.getByTestId(`component-textbox-${componentInstanceId}`)).toHaveText(textToType);
    });

    test("Re-sequence Units", async ({ tester, page }) => {
        tester.isGoingTo("Get the current order of units");
        const initialUnitOrder = await unitPlanner.getUnitOrder();

        tester.isGoingTo("Verify Persuasion and the Media to be below Romeo and Juliet");
        expect(initialUnitOrder.indexOf(romeoUnitId.toString())).toBeLessThan(
            initialUnitOrder.indexOf(persuasionUnitId.toString()),
        );

        tester.isGoingTo("drag Persuasion and the Media above Romeo and Juliet");
        await unitPlanner.dragAndDropUnit(persuasionUnitId, romeoUnitId);
        const newUnitOrder = await unitPlanner.getUnitOrder();

        tester.isGoingTo("verify that the units have been correctly reordered");
        expect(newUnitOrder.indexOf(persuasionUnitId.toString())).toBeLessThan(
            newUnitOrder.indexOf(romeoUnitId.toString()),
        );

        await expect(page.getByTestId("toast")).toHaveAttribute("data-test-is-success", "true");

        await page.reload();

        const finalUnitOrder = await unitPlanner.getUnitOrder();
        expect(finalUnitOrder).toEqual(newUnitOrder);
    });

    test("Create, modifying and deleting learning activities", async ({ tester, page, db }) => {
        const assessmentForm = new AssessmentForm(tester, page);

        tester.isGoingTo("check all options are presented under Create Activity menu");
        await unitPlanner.clickCreateActivityButton(persuasionUnitId);
        await unitPlanner.checkCreateActivityOptions("project", persuasionUnitId);
        await unitPlanner.checkCreateActivityOptions("dueWork", persuasionUnitId);
        await unitPlanner.checkCreateActivityOptions("quiz", persuasionUnitId);
        await unitPlanner.checkCreateActivityOptions("task", persuasionUnitId);
        await unitPlanner.checkCreateActivityOptions("lessonPlan", persuasionUnitId);

        tester.isGoingTo("create a due work for Persuasion and Media unit via unit planner");
        await unitPlanner.clickCreateActivityOption("dueWork", persuasionUnitId);

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

        tester.isGoingTo("check all options are presented under the activity menu");
        await unitPlanner.clickActivityDotMenu(assessmentId);
        await unitPlanner.checkActivityMenuOptions(assessmentId);

        tester.isGoingTo("Modify a due work activity via unit planner");
        await unitPlanner.clickActivityModify(assessmentId);
        await assessmentForm.setTitle("Updated via unit planner");
        await assessmentForm.update();
        tester.isGoingTo(
            "checking the activity is presented updated in the learning activities list in the unit planner",
        );
        await expect(page.getByTestId("unit-planner-textbox-action-create-17-overview-1")).toBeVisible();
        await expect(page.getByText("Updated via unit planner")).toBeVisible();

        tester.isGoingTo("Delete the due work assessment via unit planner");
        await unitPlanner.clickActivityDotMenu(assessmentId);
        await unitPlanner.clickActivityDelete(assessmentId);
        tester.isGoingTo("checking the activity is not presented in the learning activities list in the unit planner");
        await expect(page.getByText("Test Due Activity via unit planner")).not.toBeVisible();
        await expect(page.getByText("Updated via unit planner")).not.toBeVisible();
    });

    test("Select, modify and remove curriculum codes", async ({ tester, page }) => {
        const curriculumSelector = new CurriculumSelector(tester, page);

        tester.isGoingTo("select curriculum codes for the To Kill a Mockingbird unit");
        await unitPlanner.clickSelectCurriculumLink(toKillMockingbirdId); // Open the select courses modal
        await curriculumSelector.clickAllTab(); // Click the All tab
        await curriculumSelector.browseToNode([247, 248, 250, 251]);
        await curriculumSelector.selectNodes([257]); // Select the first node
        await curriculumSelector.saveModal(); // Select Select, closing the modal

        tester.isGoingTo("check the selected curriculum codes are visible");
        await expect(page.locator(`a[href="#257"]`)).toBeVisible(); // the curriculum code link
        await expect(page.locator("div.unit-planner")).toContainText("[ACHHK078]");
        await expect(page.locator("div.unit-planner")).toContainText("Year 4 History");
        await expect(page.locator("div.unit-planner")).toContainText("First Contacts");

        tester.isGoingTo("check the curriculum codes are visible in the curriculum overview tab");
        await unitPlanner.clickUnitPlannerTabs("Curriculum Overview");
        await expect(page.locator(`a[href="#257"]`)).toBeVisible(); // the curriculum code link
        await expect(page.locator("div.unit-planner")).toContainText("[ACHHK078]");
        await expect(page.locator("div.unit-planner")).toContainText("Year 4 History");
        await expect(page.locator("div.unit-planner")).toContainText("First Contacts");

        tester.isGoingTo("check the curriculum codes are visible in the unit page");
        await unitPlanner.clickUnitLink(toKillMockingbirdId);
        await expect(
            page.locator(
                `div[class="component-container Schoolbox_Curriculum_Component_CurriculumOverviewController"]`,
            ),
        ).toBeVisible();
        await expect(page.getByTestId("fn-list-container")).toContainText("[ACHHK078]");
        await expect(page.getByTestId("fn-list-container")).toContainText("Year 4 History");

        tester.isGoingTo("delete the node via unit planner and ensure it is not visible");
        await unitPlanner.goto(6);
        await unitPlanner.clickUnitPlannerTabs("All");
        await unitPlanner.clickRemoveNodeIcon(257);
        await unitPlanner.clickConfirmDeleteNodeButton(folderId);
        await expect(page.locator(`a[href="#257"]`)).not.toBeVisible(); // the curriculum code link
        await expect(page.locator("div.unit-planner")).not.toContainText("[ACHHK078]");
        await expect(page.locator("div.unit-planner")).not.toContainText("Year 4 History");
        await expect(page.locator("div.unit-planner")).not.toContainText("First Contacts");

        tester.isGoingTo("check the curriculum codes are not visible in the curriculum overview tab");
        await unitPlanner.clickUnitPlannerTabs("Curriculum Overview");
        await expect(page.locator(`a[href="#257"]`)).not.toBeVisible(); // the curriculum code link
        await expect(page.locator("div.unit-planner")).not.toContainText("[ACHHK078]");
        await expect(page.locator("div.unit-planner")).not.toContainText("Year 4 History");
        await expect(page.locator("div.unit-planner")).not.toContainText("First Contacts");

        tester.isGoingTo("check the curriculum codes are not visible in the unit page");
        await unitPlanner.clickUnitLink(toKillMockingbirdId);
        await expect(
            page.locator(
                `div[class="component-container Schoolbox_Curriculum_Component_CurriculumOverviewController"]`,
            ),
        ).toBeVisible();
        await expect(page.getByTestId("fn-list-container")).not.toBeVisible();
        await expect(page.locator("div.empty-state")).toBeVisible();
    });

    test("Create new units on empty course", async ({ tester, page, db }) => {
        const courseName = "Party time";
        const firstUnitName = "Soft party";
        const secondUnitName = "Hard party";

        try {
            // switch to admin so can later delete the created course
            await tester.loginAsAdmin();

            tester.isGoingTo("navigate to course creation page");
            await page.goto("/course/new");

            tester.isGoingTo(`fill in course name with '${courseName}'`);
            await page.getByRole("textbox", { name: "Name *" }).fill(courseName);

            tester.isGoingTo("select English faculty folder");
            await page.getByRole("link", { name: " (Click to select)" }).click();
            await page.getByRole("treeitem", { name: " English" }).click();
            await page.getByRole("button", { name: "Use this folder" }).click();

            tester.isGoingTo("click Next to create the course and go to unit planner");
            await page.getByRole("button", { name: "Next" }).click();

            tester.isGoingTo("confirm the empty units message is visible");
            await expect(page.getByText("This course currently does not have any units")).toBeVisible();

            tester.isGoingTo("confirm the create units modal is immediatey visible");
            await expect(page.getByTestId(`up-add-units-title`)).toBeVisible();

            tester.isGoingTo("create both units using the modal and verify they are visible");
            await unitPlanner.createUnitsInModal([firstUnitName, secondUnitName]);
        } finally {
            tester.isGoingTo("delete the units and course created");
            await UnitPlanner.dbDeleteUnit(db, firstUnitName);
            await UnitPlanner.dbDeleteUnit(db, secondUnitName);
            await UnitPlanner.dbDeleteCourse(db, courseName);
        }
    });

    test("modify unit name", async ({ tester, page, db }) => {
        const courseId = 11; // Year 5
        const modifyUnitId = 30; // "English"
        const modifyFolderId = 144; // "English" folder ID
        const unitNameChange = "Ancient English";
        try {
            tester.isGoingTo("visit the Year 5 unit planner page");
            await page.goto(`/course/plan/${courseId}`);

            tester.isGoingTo("click on the unit action button for unit 30");
            await page.getByTestId("sbx-pop-out-unit-cell-unit-action-30").click();

            tester.isGoingTo("wait for dropdown and click the modify button");
            await page.getByTestId(`up-unit-cell-unit-action-${modifyUnitId}-modify-link`).click();

            tester.isGoingTo(`change the folder name to '${unitNameChange}'`);
            await page.getByTestId("folder-form-input-name").fill(unitNameChange);

            tester.isGoingTo("click the Modify Folder button");
            await page.getByTestId("folder-form-submit").click();

            tester.isGoingTo(`verify that the unit name has been changed to '${unitNameChange}'`);
            await expect(page.getByTestId(`unit-planner-unit-link-${modifyUnitId}`)).toHaveText(unitNameChange);
        } finally {
            tester.isGoingTo(`reset the unit name back to 'English' for unit ${modifyUnitId}`);
            await db.updateInDatabase("folder", { name: "English" }, { id: modifyFolderId });
        }
    });

    test("delete unit", async ({ tester, page, db }) => {
        const courseId = 11; // Year 5
        const deleteUnitId = 34; // Unit to be deleted
        const deleteFolderId = 161;
        try {
            tester.isGoingTo("visit the Year 5 unit planner page");
            await page.goto(`/course/plan/${courseId}`);

            tester.isGoingTo("click on the unit action button for unit 34");
            await page.getByTestId("sbx-pop-out-unit-cell-unit-action-34").click();

            tester.isGoingTo("click the delete button");
            await page.getByTestId(`up-unit-cell-unit-action-${deleteUnitId}-delete-button`).click();

            tester.isGoingTo("confirm the deletion in the modal");
            await page.getByTestId("up-delete-unit-delete").click();

            tester.isGoingTo("verify that the toast with the message 'Unit successfully deleted' appears");
            await expect(page.getByTestId("toast")).toHaveText("Unit successfully deleted");

            tester.isGoingTo("verify that the unit has been successfully deleted after waiting for delete animation");
            await page.waitForTimeout(1000); // Wait for delete animation to complete
            await expect(page.getByText("5MS Spelling")).not.toBeVisible();
            await expect(page.getByTestId(`sbx-pop-out-unit-cell-unit-action-${deleteUnitId}`)).not.toBeVisible();
        } finally {
            tester.isGoingTo(`restore the folder`);
            await db.updateInDatabase(
                "course_unit_specification",
                { deleted_at: null },
                { folder_id: deleteFolderId, course_specification_id: courseId },
            );
        }
    });

    test("Create new unit on existing course", async ({ tester, page, db }) => {
        const courseId = 4; // ELC
        const firstUnitName = "Happy Days";
        try {
            tester.isGoingTo("navigate to ELC unit planner page ");
            await page.goto(`/course/plan/${courseId}`);

            tester.isGoingTo("click the create units button to open the modal");
            await page.getByTestId(`unit-planner-create-units`).click();

            tester.isGoingTo("create a new unit using the modal and verify it is visible");
            await unitPlanner.createUnitsInModal([firstUnitName]);
        } finally {
            tester.isGoingTo("delete the unit created");
            await UnitPlanner.dbDeleteUnit(db, firstUnitName);
        }
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

        const textBoxID = (await db.grabFromDatabase("textbox", "id", {
            folder_id: romeoUnitFolderId,
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
            await db.dontHaveInDatabase("textbox", {
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

        baseTester.isGoingTo("remove the curriculum overview component from unit page");

        const curriculumOverviewComponentId = (await db.grabFromDatabase("component_instance", "id", {
            module: "homepage",
            identifier: folderId,
            position: "top",
            sequence: 0,
        })) as number | null;

        if (curriculumOverviewComponentId === null) {
            baseTester.isGoingTo("skip deletion as the curriculum overview component instance was not found");
            return;
        }

        baseTester.isGoingTo(`delete the component instance with ID ${curriculumOverviewComponentId}`);
        await db.dontHaveInDatabase("component_config_instance", {
            component_instance_id: curriculumOverviewComponentId,
        });

        await db.dontHaveInDatabase("component_instance", {
            id: curriculumOverviewComponentId,
        });
    });
});
