/**
 * Test the core functionality of the curriculum coverage report page
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test curriculumSelector.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CurriculumSelector from "@schoolboxTest/playwright/support/page/curriculum/selector/CurriculumSelector";

test.describe("Curriculum Selector Tests", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("Curriculum can be selected in the 'All' tab", async ({ tester, page }) => {
        const curriculumSelector = new CurriculumSelector(tester, page);

        tester.isGoingTo("Modify the To Kill a Mockingbird unit");
        await page.goto("/resource/folder/89/modify");

        tester.isGoingTo("Choose ACELA1552 and ACELA1551 from the modal");
        await curriculumSelector.clickBrowse();
        await curriculumSelector.clickAllTab();
        await curriculumSelector.browseToNode([135, 136, 138, 143]);
        await curriculumSelector.selectNodes([146, 144]);
        await curriculumSelector.saveModal();

        tester.isGoingTo("Confirm that the curriculum is selected");
        await curriculumSelector.expectSelectedToContain("ACELA1552");
        await curriculumSelector.expectSelectedToContain("ELBE1174"); // the child node of ACELA1552
        await curriculumSelector.expectSelectedToContain("ACELA1551");
        await curriculumSelector.expectSelectedToContain("ELBE1173"); // the child node of ACELA1551
    });

    test("Curriculum can be unselected in the 'All' tab", async ({ tester, page }) => {
        const curriculumSelector = new CurriculumSelector(tester, page);

        tester.isGoingTo("Modify the To Kill a Mockingbird unit");
        await page.goto("/resource/folder/89/modify");

        tester.isGoingTo("Choose ACELA1552 and ACELA1551 from the modal");
        await curriculumSelector.clickBrowse();
        await curriculumSelector.clickAllTab();
        await curriculumSelector.browseToNode([135, 136, 138, 143]);
        await curriculumSelector.selectNodes([146]);
        await curriculumSelector.saveModal();

        tester.isGoingTo("Confirm that the curriculum is selected");
        await curriculumSelector.expectSelectedToContain("ACELA1552");
        await curriculumSelector.expectSelectedToContain("ELBE1174"); // the child node of ACELA1552

        tester.isGoingTo("Remove ACELA1552 from the selected curriculum");
        await curriculumSelector.clickBrowse();
        await curriculumSelector.selectNodes([146]);
        await curriculumSelector.saveModal();

        tester.isGoingTo("Confirm that no curriculum is selected");
        await curriculumSelector.expectSelectedToBeEmpty();
    });

    test("Tabs visible on lesson plan page linked to unit with curriculum already assigned", async ({
        tester,
        page,
    }) => {
        const curriculumSelector = new CurriculumSelector(tester, page);

        tester.isGoingTo("Add a new lesson plan to Romeo and Juliet");
        await page.goto("/learning/unitspecs/15/assessments/new?lpt=2&type=lessonPlan&returnUrl=L2NvdXJzZS9wbGFuLzY=");

        tester.isGoingTo("Open the modal and check for Text structure and organisation");
        await curriculumSelector.clickBrowse();
        await expect(curriculumSelector.getModalTabs()).toBeVisible();
        await curriculumSelector.expectUnitModalToContainText("Text structure and organisation");
        await curriculumSelector.expectUnitModalToContainText("Expressing and developing ideas");
    });

    test("Tabs visible on unit page with curriculum already assigned to course", async ({ tester, page }) => {
        const curriculumSelector = new CurriculumSelector(tester, page);

        tester.isGoingTo("Add a new lesson plan to Romeo and Juliet");
        await page.goto("/resource/folder/89/modify");

        tester.isGoingTo("Open the modal and check for Text structure and organisation");
        await curriculumSelector.clickBrowse();
        await expect(curriculumSelector.getModalTabs()).toBeVisible();
        await curriculumSelector.expectUnitModalToContainText("Text structure and organisation");
        await curriculumSelector.expectUnitModalToContainText("Expressing and developing ideas");
    });

    test("Can select nodes within the 'Current' tab", async ({ tester, page }) => {
        const curriculumSelector = new CurriculumSelector(tester, page);

        tester.isGoingTo("Add a new lesson plan to Romeo and Juliet");
        await page.goto("/learning/unitspecs/15/assessments/new?lpt=2&type=lessonPlan&returnUrl=L2NvdXJzZS9wbGFuLzY=");

        tester.isGoingTo("Choose ACELA1556 from the modal");
        await curriculumSelector.clickBrowse();
        await curriculumSelector.clickCurrentTab();
        await curriculumSelector.selectUnitNodes([153]);
        await curriculumSelector.saveModal();

        tester.isGoingTo("Confirm that the curriculum is selected");
        await curriculumSelector.expectSelectedToContain("ACELA1556");
        await curriculumSelector.expectSelectedToContain("ELBE1178"); // the child node of ACELA1556
    });

    test("Tabs not visible on lesson plan page with no unit curriculum assigned", async ({ tester, page }) => {
        const curriculumSelector = new CurriculumSelector(tester, page);

        tester.isGoingTo("Add a new lesson plan to To Kill a Mockingbird");
        await page.goto("learning/unitspecs/16/assessments/new?lpt=2&type=lessonPlan&returnUrl=L2NvdXJzZS9wbGFuLzY=");

        tester.isGoingTo("Open the modal and check for Current/All Tabs");
        await curriculumSelector.clickBrowse();
        await expect(curriculumSelector.getModalTabs()).not.toBeVisible();
    });
});
