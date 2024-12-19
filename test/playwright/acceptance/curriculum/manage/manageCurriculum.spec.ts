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
import UpdateNode from "@schoolboxTest/playwright/support/page/curriculum/manage/action/UpdateNode";

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

    test("Update Node", async ({ tester, page }) => {
        tester.isGoingTo("update Year 9 History");
        const updateNode = new UpdateNode(page, tester);
        await updateNode.clickDisplay("Year 9 History");
        await updateNode.fillName("Year 9 Ancient History");
        await updateNode.fillCode("9AH");
        await updateNode.fillDescription("1", "Here we will focus mainly on the Romans, Egyptians and Babylonians");
        await updateNode.clickUpdateButton();

        tester.isGoingTo("ensure the toast message is shown");
        await expect(page.getByTestId("toast")).toContainText("Outcome successfully updated");

        tester.isGoingTo("ensure the updated node displays correctly");
        await expect(page.getByTestId("mc-view-nodes-list")).toContainText("[9AH] Year 9 Ancient History");
    });

    test("hideDisabledNodesAndSearch", async ({ tester, page }) => {
        tester.isGoingTo("navigate to First Contacts in Year 4 History");
        const manageCurriculum = new ManageCurriculum(page, tester);
        await manageCurriculum.clickNode("Year 4 History");
        await manageCurriculum.clickNode("Year 4");
        await manageCurriculum.clickNode("Historical Knowledge and Understanding");
        await manageCurriculum.clickNode("First Contacts");

        tester.isGoingTo("search for nodes containing ACHH");
        const searchNodes = new SearchNodes(page, tester);
        await searchNodes.fillSearch("ACHH");

        tester.isGoingTo("expect 4 search results are shown");
        await searchNodes.seeResultsMatch(4);

        tester.isGoingTo("hide disabled nodes and so expect no search results are now shown");
        await searchNodes.uncheckShowDisabledNodes();
        await page.waitForSelector("[data-test-node]", { state: "hidden" });

        await searchNodes.seeResultsMatch(0);
    });

    test("Check Node Sort", async ({ tester, page, db }) => {
        tester.isGoingTo("check nodes are sorted by order, code, then name");
        tester.isGoingTo("add order: 1 to Year 4 History so it appears at the top of the list");
        await db.updateInDatabase("curriculum_node", { "`order`": 1 }, { "name like": "Year 4 History" });
        await page.reload();
        const expectedOrder = [
            "Year 4 History",
            "[9AH] Year 9 Ancient History",
            "[PT] Party Town",
            "English",
            "Years 9 and 10 French",
        ];
        const nodeNames: string[] = await page.getByTestId("view-node-name").allInnerTexts();
        // Skip the first node as it is the curriculum node
        for (let i = 1; i < expectedOrder.length; i += 1) {
            expect(nodeNames[i]).toContain(expectedOrder[i - 1]);
        }
    });

    test("Navigate With Browser Buttons", async ({ tester, page }) => {
        tester.isGoingTo("use the browser backward and forward buttons to traverse the curriculum");
        tester.isGoingTo(
            "traverse to 'Curricula > English > Year 9 > Literature > Creating literature' and ensure 'ACELT1773' is visible",
        );
        const manageCurriculum = new ManageCurriculum(page, tester);
        await manageCurriculum.clickNode("English");
        await manageCurriculum.clickNode("Year 9");
        await manageCurriculum.clickNode("Literature");
        await manageCurriculum.clickNode("Creating literature");
        await expect(page.getByTestId("mc-view-nodes-list")).toContainText("ACELT1773");

        await page.goBack();
        await page.goBack();
        await expect(page.getByTestId("mc-view-nodes-list")).toContainText("Achievement standard");

        await page.goBack();
        await page.goBack();
        await expect(page.getByTestId("mc-view-nodes-list")).toContainText("Years 9 and 10 French");

        await page.goForward();
        await page.goForward();
        await page.goForward();
        await expect(page.getByTestId("mc-view-nodes-list")).toContainText("Responding to literature");

        await page.goBack();
        await page.goBack();

        const searchNodes = new SearchNodes(page, tester);
        await searchNodes.fillSearch("ACELT1638");
        await manageCurriculum.clickNode(
            "[ACELT1638] Experiment with the ways that language features, image and sound can be adapted in literary texts, for example the effects of stereotypical characters and settings, the playfulness of humour and pun and the use of hyperlink",
            true,
        );
        await expect(page.getByTestId("mc-view-nodes-list")).toContainText("ELBE1201");

        await page.goBack();
        await expect(page.getByTestId("mc-view-nodes-list")).toContainText("Year 9");

        await page.goForward();
        await expect(page.getByTestId("mc-view-nodes-list")).toContainText("ELBE1202");
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
