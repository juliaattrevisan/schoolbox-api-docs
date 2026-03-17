import type { Locator } from "@playwright/test";
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CurriculumDescription from "@schoolboxTest/playwright/support/page/homepage/CurriculumDescription";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";

/**
 * Creates and verifies the Curriculum Description homepage component.
 * The tests cover:
 * - adding the component and setting a title
 * - selecting a single curriculum code
 * - showing all curriculum codes and descriptions
 * - clicking curriculum code links and verifying navigation
 * - hiding a disabled curriculum node for students
 *
 * Steps to execute:
 * 1. Provision sales data
 * 2. Execute npx playwright test curriculumDescription.spec.ts
 */

test.describe("Curriculum Description Component Tests", () => {
    test.describe.configure({ mode: "serial" });

    let homepage: Homepage;
    let curriculumDescription: CurriculumDescription;
    let curriculumDescriptionComponent: Locator;
    let curriculumDescriptionComponentId: number;
    const COMPONENT_TITLE = "Test Curriculum Description Automation";
    const CURRICULUM_ACELA1556 = {
        code: "ACELA1556",
        description: "Understand how punctuation is used",
        url: /\/curriculum#153/,
    };
    const CURRICULUM_ACELA1560 = {
        code: "ACELA1560",
        description: "Analyse and explain the use of symbols",
        url: /\/curriculum#161/,
    };
    const CURRICULUM_ELBE1177 = {
        code: "ELBE1177",
        description: "experimenting with the use of colons and semicolons",
        url: /\/curriculum#154/,
    };

    /**
     * Logs in as admin before each test.
     */
    test.beforeEach(async ({ tester, page }) => {
        homepage = new Homepage(tester, page);
        curriculumDescription = new CurriculumDescription(tester, page);
        await tester.loginAsAdmin();
    });

    test("Add component title and verify selected curriculum code", async ({ tester }) => {
        tester.isGoingTo("Homepage, open tab panel, click components tab");
        await homepage.goto(87);
        tester.isGoingTo("Add Curriculum Description component");
        await homepage.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_LEARNING, "Curriculum Description");
        curriculumDescriptionComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        curriculumDescriptionComponentId = await Homepage.getComponentId(curriculumDescriptionComponent);
        tester.isGoingTo(`Use component: ${curriculumDescriptionComponentId}`);
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
        await Homepage.clickComponentSettings(curriculumDescriptionComponent);
        tester.isGoingTo("Add title and select curriculum code");
        await curriculumDescription.addTitle(curriculumDescriptionComponent, COMPONENT_TITLE);
        tester.isGoingTo("Select curriculum code and update");
        await CurriculumDescription.setCurriculumCode(curriculumDescriptionComponent, CURRICULUM_ACELA1556.code);
        tester.isGoingTo("Verify selected curriculum code and description");
        await CurriculumDescription.verifyCurriculumLinkCode(curriculumDescriptionComponent, CURRICULUM_ACELA1556.code);
        await CurriculumDescription.verifyCurriculumDescriptionRow(
            curriculumDescriptionComponent,
            CURRICULUM_ACELA1556.code,
            CURRICULUM_ACELA1556.description,
        );
    });

    test("Select All and verify all curriculum rows are shown", async ({ tester }) => {
        tester.isGoingTo("Open homepage and load Curriculum Description component");
        await homepage.goto(87);
        curriculumDescriptionComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        curriculumDescriptionComponentId = await Homepage.getComponentId(curriculumDescriptionComponent);
        tester.isGoingTo(`Use component: ${curriculumDescriptionComponentId}`);
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
        await Homepage.clickComponentSettings(curriculumDescriptionComponent);
        await CurriculumDescription.setCurriculumCode(curriculumDescriptionComponent, "All");
    });

    test("Click curriculum code links and verify redirect URLs", async ({ tester, page }) => {
        tester.isGoingTo("Go to homepage");
        await homepage.goto(87);
        tester.isGoingTo("Locate the Curriculum Description component");
        curriculumDescriptionComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        tester.isGoingTo("Click curriculum code link and verify it redirects to curriculum page");
        tester.isGoingTo("Click ACELA1556 and verify URL");
        await CurriculumDescription.clickCurriculumCodeLink(curriculumDescriptionComponent, CURRICULUM_ACELA1556.code);
        await expect(page).toHaveURL(CURRICULUM_ACELA1556.url);
        await page.goBack();
        curriculumDescriptionComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        tester.isGoingTo("Click ACELA1560 and verify URL");
        await CurriculumDescription.clickCurriculumCodeLink(curriculumDescriptionComponent, CURRICULUM_ACELA1560.code);
        await expect(page).toHaveURL(CURRICULUM_ACELA1560.url);
        await page.goBack();
        curriculumDescriptionComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        tester.isGoingTo("Click ELBE1177 and verify URL");
        await CurriculumDescription.clickCurriculumCodeLink(curriculumDescriptionComponent, CURRICULUM_ELBE1177.code);
        await expect(page).toHaveURL(CURRICULUM_ELBE1177.url);
    });

    test("Disable curriculum node from the database", async ({ tester, db }) => {
        tester.isGoingTo("Disable curriculum node ELBE1177");
        await db.updateInDatabase("curriculum_node", { enabled: 0 }, { id: 155, code: "ELBE1177" });
    });

    test("Login as Jana Reed and verify student view for disabled curriculum node", async ({ tester }) => {
        tester.isGoingTo("Impersonate Jana Reed and open homepage");
        await tester.impersonate("Jana Reed");
        await homepage.goto(87);
        tester.isGoingTo(
            "Login as student and verify the component title is visible and curriculum rows are shown as plain text only",
        );
        curriculumDescriptionComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        await homepage.seeInComponentHeading(COMPONENT_TITLE, Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        await expect(curriculumDescriptionComponent.locator('a[data-test^="curriculum-code-"]')).toHaveCount(0);
        await expect(curriculumDescriptionComponent).toContainText(CURRICULUM_ACELA1556.code);
        await expect(curriculumDescriptionComponent).toContainText(CURRICULUM_ACELA1556.description);
        await expect(curriculumDescriptionComponent).toContainText(CURRICULUM_ACELA1560.code);
        await expect(curriculumDescriptionComponent).toContainText(CURRICULUM_ACELA1560.description);
        tester.isGoingTo("Verify ELBE1177 code and description are not visible to the student");
        await expect(curriculumDescriptionComponent).not.toContainText(CURRICULUM_ELBE1177.code);
        await expect(curriculumDescriptionComponent).not.toContainText(CURRICULUM_ELBE1177.description);
    });

    /**
     * Restores the curriculum node and removes the Curriculum Description component after the suite finishes.
     */
    test.afterAll(async ({ baseTester, db, api }) => {
        baseTester.isGoingTo("clean up: remove Curriculum Description component and restore curriculum node");
        await db.updateInDatabase("curriculum_node", { enabled: 1 }, { id: 155, code: "ELBE1177" });
        await api.deleteHomepageComponent(87, curriculumDescriptionComponentId);
    });
});
