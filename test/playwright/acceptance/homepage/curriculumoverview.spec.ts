
import type { Locator } from "@playwright/test";
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CurriculumOverview from "@schoolboxTest/playwright/support/page/homepage/CurriculumOverview";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";

test.describe("Curriculum Overview Component Tests", () => {
    test.describe.configure({ mode: "serial" });

    let homepage: Homepage;
    let curriculumOverview: CurriculumOverview;
    let curriculumOverviewComponent: Locator;

    const curriculumOverviewTitle = "Test Curriculum Overview Automation";

    test.beforeEach(async ({ tester, page }) => {
        homepage = new Homepage(tester, page);
        curriculumOverview = new CurriculumOverview(tester, page);
        await tester.loginAsAdmin();
    });

    test("Verify Curriculum Overview Component", async ({ tester, page }) => {
        tester.isGoingTo("Homepage, open tab panel, click components tab");
        await homepage.goto(87);

        tester.isGoingTo("Add Curriculum Overview component");
        await homepage.addComponent(Homepage.ELEMENT_COMPONENT_SECTION_LEARNING, "Curriculum Description",);
        curriculumOverviewComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1,);
        const componentEnglishId = await Homepage.getComponentId(curriculumOverviewComponent);
        tester.isGoingTo(`Use component: ${componentEnglishId}`);
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
        await Homepage.clickComponentSettings(curriculumOverviewComponent);
        tester.isGoingTo("Add Title");
        await curriculumOverview.addTitle(curriculumOverviewComponent, curriculumOverviewTitle);
        tester.isGoingTo("Select curriculum code and update");
        await CurriculumOverview.setLinkLimit(curriculumOverviewComponent, "ACELA1556");
        tester.isGoingTo("Verify updated title exists");
        await homepage.seeInComponentHeading(curriculumOverviewTitle, Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1,);
        tester.isGoingTo("Verify curriculum description is visible");
        await CurriculumOverview.verifyCurriculumCodeWithDescription(curriculumOverviewComponent, "Understand how punctuation is used");
        tester.isGoingTo("Verify curriculum link code ACELA1556 is visible");
        await CurriculumOverview.verifyCurriculumLinkCode(curriculumOverviewComponent, "ACELA1556");

        await Homepage.clickComponentSettings(curriculumOverviewComponent);
        tester.isGoingTo("Select All from curriculum dropdown and update");
        await CurriculumOverview.setLinkLimit(curriculumOverviewComponent, "All");
        tester.isGoingTo("Verify all curriculum rows are shown");
        await CurriculumOverview.verifyCurriculumLinkCode(curriculumOverviewComponent, "ACELA1556");
        await CurriculumOverview.verifyCurriculumCodeWithDescription(curriculumOverviewComponent, "Understand how punctuation is used",);
        await CurriculumOverview.verifyCurriculumLinkCode(curriculumOverviewComponent, "ACELA1560");
        await CurriculumOverview.verifyCurriculumCodeWithDescription(curriculumOverviewComponent, "Analyse and explain the use of symbols",);
        await CurriculumOverview.verifyCurriculumLinkCode(curriculumOverviewComponent, "ELBE1177");
        await CurriculumOverview.verifyCurriculumCodeWithDescription(curriculumOverviewComponent, "experimenting with the use of colons and semicolons",);



        tester.isGoingTo("Click curriculum code link and verify it redirects to curriculum page");
        tester.isGoingTo("Click ACELA1556 and verify URL");
        await curriculumOverview.clickCurriculumCodeLink(curriculumOverviewComponent, "ACELA1556");
        await expect(page).toHaveURL(/\/curriculum#153/);

        await page.goBack();
        curriculumOverviewComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);

        tester.isGoingTo("Click ACELA1560 and verify URL");
        await curriculumOverview.clickCurriculumCodeLink(curriculumOverviewComponent, "ACELA1560");
        await expect(page).toHaveURL(/\/curriculum#161/);

        await page.goBack();
        curriculumOverviewComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);

        tester.isGoingTo("Click ELBE1177 and verify URL");
        await curriculumOverview.clickCurriculumCodeLink(curriculumOverviewComponent, "ELBE1177");
        await expect(page).toHaveURL(/\/curriculum#154/);








    });
});