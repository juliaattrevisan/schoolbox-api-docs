
import type { Locator } from "@playwright/test";
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CurriculumOverview from "@schoolboxTest/playwright/support/page/homepage/CurriculumOverview";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";

test.describe("Curriculum Overview Component Tests", () => {
    test.describe.configure({ mode: "serial" });

    let homepage: Homepage;
    let curriculumOverview: CurriculumOverview;
    let curriculumOverviewComponent: Locator;
    let curriculumOverviewComponentId: number;
    const COMPONENT_TITLE = "Test Curriculum Overview Automation";
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

    test.beforeEach(async ({ tester, page }) => {
        homepage = new Homepage(tester, page);
        curriculumOverview = new CurriculumOverview(tester, page);
        await tester.loginAsAdmin();
    });

    test("Add component title and verify selected curriculum code", async ({ tester }) => {
        tester.isGoingTo("Homepage, open tab panel, click components tab");
        await homepage.goto(87);
        tester.isGoingTo("Add Curriculum Overview component");
        await homepage.addComponent(Homepage.ELEMENT_COMPONENT_SECTION_LEARNING, "Curriculum Description",);
        curriculumOverviewComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1,);
        curriculumOverviewComponentId = await Homepage.getComponentId(curriculumOverviewComponent);
        tester.isGoingTo(`Use component: ${curriculumOverviewComponentId}`);
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
        await Homepage.clickComponentSettings(curriculumOverviewComponent);
        tester.isGoingTo("Add Title and select curriculum code");
        await curriculumOverview.addTitle(curriculumOverviewComponent, COMPONENT_TITLE);
        tester.isGoingTo("Select curriculum code and update");
        await CurriculumOverview.setCurriculumCode(curriculumOverviewComponent, CURRICULUM_ACELA1556.code,);
        tester.isGoingTo("Verify updated title, selected curriculum code, and description");
        await CurriculumOverview.verifyCurriculumLinkCode(curriculumOverviewComponent, CURRICULUM_ACELA1556.code,);
        await CurriculumOverview.verifyCurriculumDescriptionRow(curriculumOverviewComponent, CURRICULUM_ACELA1556.description,);
    });

    test("Select All and verify all curriculum rows are shown", async ({ tester }) => {
        tester.isGoingTo("Open homepage and load Curriculum Overview component");
        await homepage.goto(87);
        curriculumOverviewComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1,);
        curriculumOverviewComponentId = await Homepage.getComponentId(curriculumOverviewComponent);
        tester.isGoingTo(`Use component: ${curriculumOverviewComponentId}`);
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
        await Homepage.clickComponentSettings(curriculumOverviewComponent);
        await CurriculumOverview.setCurriculumCode(curriculumOverviewComponent, "All");
        tester.isGoingTo("Verify component shows exactly 3 curriculum codes");
        await expect(curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_CURRICULUM_CODE_LINKS,),).toHaveCount(3);
        tester.isGoingTo("Verify component shows exactly 3 curriculum description rows");
        await expect(curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_CURRICULUM_DESCRIPTION_ROWS,),).toHaveCount(3);
        tester.isGoingTo("Verify all curriculum rows are shown");
        await CurriculumOverview.verifyCurriculumLinkCode(curriculumOverviewComponent, CURRICULUM_ACELA1556.code);
        await CurriculumOverview.verifyCurriculumDescriptionRow(curriculumOverviewComponent, CURRICULUM_ACELA1556.description);
        await CurriculumOverview.verifyCurriculumLinkCode(curriculumOverviewComponent, CURRICULUM_ACELA1560.code);
        await CurriculumOverview.verifyCurriculumDescriptionRow(curriculumOverviewComponent, CURRICULUM_ACELA1560.description);
        await CurriculumOverview.verifyCurriculumLinkCode(curriculumOverviewComponent, CURRICULUM_ELBE1177.code);
        await CurriculumOverview.verifyCurriculumDescriptionRow(curriculumOverviewComponent, CURRICULUM_ELBE1177.description);
    });

    test("Click curriculum code links and verify redirect URLs", async ({ tester, page }) => {
        tester.isGoingTo("Open homepage and load Curriculum Overview component");
        await homepage.goto(87);
        tester.isGoingTo("Add Curriculum Overview component");
        curriculumOverviewComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1,);
        tester.isGoingTo("Click curriculum code link and verify it redirects to curriculum page");
        tester.isGoingTo("Click ACELA1556 and verify URL");
        await curriculumOverview.clickCurriculumCodeLink(curriculumOverviewComponent, CURRICULUM_ACELA1556.code);
        await expect(page).toHaveURL(CURRICULUM_ACELA1556.url);
        await page.goBack();
        curriculumOverviewComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        tester.isGoingTo("Click ACELA1560 and verify URL");
        await curriculumOverview.clickCurriculumCodeLink(curriculumOverviewComponent, CURRICULUM_ACELA1560.code);
        await expect(page).toHaveURL(CURRICULUM_ACELA1560.url);
        await page.goBack();
        curriculumOverviewComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        tester.isGoingTo("Click ELBE1177 and verify URL");
        await curriculumOverview.clickCurriculumCodeLink(curriculumOverviewComponent, CURRICULUM_ELBE1177.code);
        await expect(page).toHaveURL(CURRICULUM_ELBE1177.url);
    });

    test("Disable curriculum node from the database", async ({ tester, db }) => {
        tester.isGoingTo("Disable curriculum node ELBE1177");
        await db.updateInDatabase("curriculum_node", { enabled: 0 }, { id: 155, code: "ELBE1177" },);
    });

    test("Login as Jana Reed and verify student view for disabled curriculum node", async ({ tester }) => {
        tester.isGoingTo("Impersonate Jana Reed and open homepage");
        await tester.impersonate("Jana Reed");
        await homepage.goto(87);
        tester.isGoingTo("Login as student and verify the component title is visible and curriculum rows are shown as plain text only");
        curriculumOverviewComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        await homepage.seeInComponentHeading(COMPONENT_TITLE, Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1,);
        await expect(curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_CURRICULUM_CODE_LINKS,),).toHaveCount(0);
        await expect(curriculumOverviewComponent).toContainText(CURRICULUM_ACELA1556.code);
        await expect(curriculumOverviewComponent).toContainText(CURRICULUM_ACELA1556.description);
        await expect(curriculumOverviewComponent).toContainText(CURRICULUM_ACELA1560.code);
        await expect(curriculumOverviewComponent).toContainText(CURRICULUM_ACELA1560.description);
        tester.isGoingTo("Verify ELBE1177 code and description are not visible to the student");
        await expect(curriculumOverviewComponent).not.toContainText(CURRICULUM_ELBE1177.code);
        await expect(curriculumOverviewComponent).not.toContainText(CURRICULUM_ELBE1177.description);
    });

    test.afterAll(async ({ baseTester, db, api }) => {
        baseTester.isGoingTo("clean up: remove Curriculum Overview component and restore curriculum node");
        await db.updateInDatabase("curriculum_node", { enabled: 1 }, { id: 155, code: "ELBE1177" });
        await api.deleteHomepageComponent(87, curriculumOverviewComponentId);
    });


});