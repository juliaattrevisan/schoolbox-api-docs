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

    test.afterEach(async ({ tester }) => {
        await tester.logout();
    });
});
