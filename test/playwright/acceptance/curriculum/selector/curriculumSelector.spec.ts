/**
 * Test the core functionality of the curriculum coverage report page
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test curriculumCoverageReport.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CurriculumSelector from "@schoolboxTest/playwright/support/page/curriculum/selector/CurriculumSelector";

test.describe("Curriculum Selector Tests", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("Curriculum can be selected", async ({ tester, page }) => {
        const curriculumSelector = new CurriculumSelector(page);

        tester.isGoingTo("Modify the To Kill a Mockingbird unit");
        await page.goto("/resource/folder/89/modify");

        tester.isGoingTo("Choose ACELA1552 and ACELA1551 from the modal");
        await curriculumSelector.clickBrowse();
        await curriculumSelector.browseToNode([135, 136, 138, 143]);
        await curriculumSelector.selectNodes([146, 144]);
        await curriculumSelector.saveModal();

        tester.isGoingTo("Confirm that the curriculum is selected");
        await curriculumSelector.expectSelectedToContain("ACELA1552");
        await curriculumSelector.expectSelectedToContain("ELBE1174"); // the child node of ACELA1552
        await curriculumSelector.expectSelectedToContain("ACELA1551");
        await curriculumSelector.expectSelectedToContain("ELBE1173"); // the child node of ACELA1551
    });

    test("Curriculum can be unselected", async ({ tester, page }) => {
        const curriculumSelector = new CurriculumSelector(page);

        tester.isGoingTo("Modify the To Kill a Mockingbird unit");
        await page.goto("/resource/folder/89/modify");

        tester.isGoingTo("Choose ACELA1552 and ACELA1551 from the modal");
        await curriculumSelector.clickBrowse();
        await curriculumSelector.browseToNode([135, 136, 138, 143]);
        await curriculumSelector.selectNodes([146]);
        await curriculumSelector.saveModal();

        tester.isGoingTo("Confirm that the curriculum is selected");
        await curriculumSelector.expectSelectedToContain("ACELA1552");
        await curriculumSelector.expectSelectedToContain("ELBE1174"); // the child node of ACELA1552

        tester.isGoingTo("Remove ACELA1552 from the selected curriculum");
        await curriculumSelector.selectNodes([146]);
        await curriculumSelector.saveModal();

        tester.isGoingTo("Confirm that no curriculum is selected");
        await curriculumSelector.expectSelectedToBeEmpty();
    });
});
