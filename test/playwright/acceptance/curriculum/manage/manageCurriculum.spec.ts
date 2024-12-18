/**
 * This test creates a curriculum, adds a subsection, disable, updates and delete nodes, search nodes (with disabled nodes toggle on and off),
 * check the nodes sorting, navigates through the nodes by clicking the nodes and using the browser buttons (back and forward)
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test manageCurriculum.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import ManageCurriculum from "@schoolboxTest/playwright/support/page/curriculum/manage/ManageCurriculum";
import AddNewCurriculum from "@schoolboxTest/playwright/support/page/curriculum/manage/action/AddNewCurriculum";
import AddNewSubsection from "@schoolboxTest/playwright/support/page/curriculum/manage/action/AddNewSubsection";
import DisableNodes from "@schoolboxTest/playwright/support/page/curriculum/manage/action/DisableNodes";
import DeleteNode from "@schoolboxTest/playwright/support/page/curriculum/manage/action/DeleteNode";
import SearchNodes from "@schoolboxTest/playwright/support/page/curriculum/manage/action/SearchNodes";

test.describe("Manage Curriculum Tests", () => {
    let mc: ManageCurriculum;

    test.beforeEach(async ({ tester, page }) => {
        await tester.loginAsAdmin();

        mc = new ManageCurriculum(page, tester);
        await mc.goto();
    });

    test("Add New Curriculum", async ({ tester, page }) => {
        tester.isGoingTo("add a new curriculum");
        const addNewCurriculum = new AddNewCurriculum(page, tester);
        await addNewCurriculum.clickDisplay();

        tester.isGoingTo("ensure the add button is disabled until I add a name");
        await addNewCurriculum.seeAddButtonIsDisabled();
        await addNewCurriculum.fillName("Party Town");
        await addNewCurriculum.fillCode("PT");
        await addNewCurriculum.clickAddButton();

        tester.isGoingTo("ensure the toast message is shown");
        await expect(page.getByTestId("toast")).toContainText("Curriculum successfully added");

        tester.isGoingTo("ensure I can see the new curriculum");
        await addNewCurriculum.seeCurriculumExists("[PT] Party Town");
    });

    test("Add New Subsection", async ({ tester, page }) => {
        tester.isGoingTo("add a new subsection to English");
        const addNewSubsection = new AddNewSubsection(page, tester);
        await addNewSubsection.clickDisplay("English");

        tester.isGoingTo("ensure the add button is disabled until I add a name");
        await addNewSubsection.seeAddButtonIsDisabled();
        await addNewSubsection.fillName("Grammar");
        await addNewSubsection.fillCode("GR");
        await addNewSubsection.clickAddButton();

        tester.isGoingTo("ensure the toast message is shown");
        await expect(page.getByTestId("toast")).toContainText("Subsection successfully added");

        tester.isGoingTo("ensure I can see the new subsection");
        await addNewSubsection.seeSubsectionExists("[GR] Grammar");
    });

    test("Disable Nodes", async ({ tester, page }) => {
        tester.isGoingTo("disable Year 4 History and Year 9 History");
        const manageCurriculum = new ManageCurriculum(page, tester);
        const disableNodes = new DisableNodes(page, tester);

        // Check Year 4 and Year 9 History nodes
        await manageCurriculum.checkNode("Year 4 History");
        await manageCurriculum.checkNode("Year 9 History");

        // Click Disable button
        await disableNodes.clickDisableButton();

        tester.isGoingTo("ensure the confirmation modal appears");
        await disableNodes.waitForConfirmModal();

        tester.isGoingTo("confirm the disable action");
        await disableNodes.clickConfirmButton();

        tester.isGoingTo("ensure the toast message is shown");
        await expect(page.getByTestId("toast")).toContainText("Outcomes successfully disabled");
    });

    test("Delete Node", async ({ tester, page }) => {
        tester.isGoingTo("delete the Year 12 French Node");
        const manageCurriculum = new ManageCurriculum(page, tester);
        const deleteNode = new DeleteNode(page, tester);

        // Check Year 12 French node
        await manageCurriculum.checkNode("Year 12 French");

        // Click Delete button
        await deleteNode.clickDeleteButton();

        tester.isGoingTo("ensure the confirmation modal appears");
        await deleteNode.waitForConfirmModal();

        tester.isGoingTo("confirm the delete action");
        await deleteNode.clickConfirmButton();

        tester.isGoingTo("ensure the toast message is shown");
        await expect(page.getByTestId("toast")).toContainText("Outcome successfully deleted");
    });

    test("Search Nodes", async ({ tester, page }) => {
        tester.isGoingTo("search for nodes containing 'historical' in Year 4 History");
        const manageCurriculum = new ManageCurriculum(page, tester);
        await manageCurriculum.clickNode("Year 4 History");

        const searchNodes = new SearchNodes(page, tester);
        await searchNodes.fillSearch("historical");

        tester.isGoingTo("expect 4 search results are shown");
        await searchNodes.seeResultsMatch(4);
    });

    test.afterEach(async ({ tester }) => {
        await tester.logout();
    });

    /**
     * Soft deleting data to not impact the tests
     */
    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo(
            "update delete_at for the data created in this test so it does impact next suite of tests",
        );
        await db.updateInDatabase(
            "curriculum_node",
            { deleted_at: "2022-01-15 11:22:34" },
            { "name like": "Party Town" },
        );
        await db.updateInDatabase("curriculum_node", { deleted_at: "2022-01-15 11:22:34" }, { "name like": "Grammar" });

        await db.updateInDatabase("curriculum_node", { "`order`": 0 }, { "name like": "Year 4 History" });

        await db.updateInDatabase(
            "curriculum_node",
            { name: "Year 9 History" },
            { "name like": "Year 9 Ancient History" },
        );

        await db.updateInDatabase("curriculum_node", { code: "" }, { "name like": "Year 9 History" });

        await db.updateInDatabase("curriculum_node", { description: "" }, { "name like": "Year 9 History" });

        await db.updateInDatabase("curriculum_node", { deleted_at: null }, { "name like": "Year 12 French" });

        await db.updateInDatabase("curriculum_node", { enabled: 1 }, { "name like": "Year 4 History" });

        await db.updateInDatabase("curriculum_node", { enabled: 1 }, { "name like": "Year 9 History" });
    });
});
