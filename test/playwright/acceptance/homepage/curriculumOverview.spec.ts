import type { Locator, Page } from "@playwright/test";
import { test } from "@schoolboxTest/playwright/support/integrations";
import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import CurriculumOverview from "@schoolboxTest/playwright/support/page/homepage/CurriculumOverview";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import UnitPlanner from "@schoolboxTest/playwright/support/page/unitPlanner/UnitPlanner";

/**
 * Creates a Curriculum Overview homepage component and verifies curriculum mapping and node detail display.
 *
 * Steps to execute:
 * 1. Provision sales.tar.gz data
 * 2. Execute DOCKER=1 npx playwright test curriculumOverview.spec.ts
 */

test.describe.serial("Curriculum Overview Component Tests", () => {
    const HOMEPAGE_ID = 83;
    const CURRICULUM_PATH = [247, 248, 250, 251];
    const CURRICULUM_NODE_ACHHK078 = 257;
    const CURRICULUM_NODE_ELBH121 = 259;
    const CURRICULUM_NODE_ACHHK079 = 261;
    const ACHHK078_TITLE =
        "[ACHHK078] The journey(s) of AT LEAST ONE world navigator, explorer or trader up to the late eighteenth century, including their contacts with other societies and any impacts.";
    const ACHHK078_BREADCRUMB = "Year 4 History > Year 4 > Historical Knowledge and Understanding > First Contacts";

    let curriculumOverviewComponentId: number | null = null;
    let homepage: Homepage;
    let curriculumOverview: CurriculumOverview;
    let curriculumOverviewComponent: Locator;
    let unitPlanner: UnitPlanner;

    /**
     * Adds a Curriculum Overview component on the homepage and retrieves its ID for testing.
     */

    test.beforeAll(async ({ baseURL, browser }) => {
        const setupContext = await browser.newContext({
            baseURL,
            ignoreHTTPSErrors: true,
            viewport: { width: 1920, height: 1080 },
        });
        const setupPage: Page = await setupContext.newPage();
        const setupTester = new AcceptanceTester(setupPage);
        const setupHomepage = new Homepage(setupTester, setupPage);

        try {
            await setupTester.loginAsAdmin();
            await setupHomepage.goto(HOMEPAGE_ID);
            await setupHomepage.openLinkMenu();
            await setupHomepage.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_LEARNING, "Curriculum Overview");

            const component = setupHomepage.locateComponentByName("Curriculum Overview").first();
            curriculumOverviewComponentId = await Homepage.getComponentId(component);
        } finally {
            await setupContext.close();
        }
    });

    test.beforeEach(async ({ tester, page }) => {
        homepage = new Homepage(tester, page);
        curriculumOverview = new CurriculumOverview(tester, page);
        unitPlanner = new UnitPlanner(tester, page);

        await tester.loginAsAdmin();
        await homepage.goto(HOMEPAGE_ID);
        if (curriculumOverviewComponentId === null) {
            throw new Error("Curriculum Overview component was not created");
        }
        curriculumOverviewComponent = page.locator(`#component${curriculumOverviewComponentId}`);
    });

    test("Mapping curriculum codes via curriculum overview component", async ({ db, page, tester }) => {
        tester.isGoingTo("clear existing curriculum mappings for the homepage");
        await db.dontHaveInDatabase("curriculum_node_for_category", { category_id: HOMEPAGE_ID });
        await homepage.goto(HOMEPAGE_ID);
        curriculumOverviewComponent = page.locator(`#component${curriculumOverviewComponentId}`);

        tester.isGoingTo("map curriculum codes through the Curriculum Overview component");
        await curriculumOverview.mapCurriculumCodes(curriculumOverviewComponent, HOMEPAGE_ID, CURRICULUM_PATH, [
            CURRICULUM_NODE_ACHHK078,
            CURRICULUM_NODE_ACHHK079,
        ]);

        tester.isGoingTo("verify mapped curriculum codes are visible on the component");
        await CurriculumOverview.expectListToContainText(curriculumOverviewComponent, "[ACHHK078]");
        await CurriculumOverview.expectListToContainText(curriculumOverviewComponent, "[ACHHK079]");
        await CurriculumOverview.expectListToContainText(curriculumOverviewComponent, "Year 4 History");
        await CurriculumOverview.expectListToContainText(curriculumOverviewComponent, "First Contacts");

        tester.isGoingTo(
            "open the ACHHK078 curriculum details modal to check the curriculum details modal title and breadcrumb",
        );
        await CurriculumOverview.clickCurriculumCode(curriculumOverviewComponent, CURRICULUM_NODE_ACHHK078);
        await curriculumOverview.expectDetailsModalTitle(ACHHK078_TITLE);
        await curriculumOverview.expectDetailsModalBreadcrumb(ACHHK078_BREADCRUMB);
    });

    test("Checking disabled curriculum and removing the curriculum via curriculum overview component", async ({
        db,
        page,
        tester,
    }) => {
        try {
            tester.isGoingTo("disable ACHHK078 and reload the component");
            await db.updateInDatabase("curriculum_node", { enabled: 0 }, { id: CURRICULUM_NODE_ACHHK078 });
            await homepage.goto(HOMEPAGE_ID);

            tester.isGoingTo("verify the disabled curriculum icon is visible on the component");
            await CurriculumOverview.expectListToContainText(curriculumOverviewComponent, "[ACHHK078]");
            await CurriculumOverview.expectDisabledIconToBeVisible(
                curriculumOverviewComponent,
                CURRICULUM_NODE_ACHHK078,
            );

            tester.isGoingTo("remove ACHHK078 via the Curriculum Overview component");
            await unitPlanner.clickRemoveNodeIcon(CURRICULUM_NODE_ACHHK078);
            await unitPlanner.clickConfirmDeleteNodeButton(HOMEPAGE_ID);

            tester.isGoingTo("verify ACHHK078 and its child ELBH121 are no longer visible");
            await CurriculumOverview.expectCurriculumCodeNotToBeVisible(
                curriculumOverviewComponent,
                CURRICULUM_NODE_ACHHK078,
            );
            await CurriculumOverview.expectCurriculumCodeNotToBeVisible(
                curriculumOverviewComponent,
                CURRICULUM_NODE_ELBH121,
            );
            await CurriculumOverview.expectListNotToContainText(curriculumOverviewComponent, "[ACHHK078]");
            await CurriculumOverview.expectListNotToContainText(curriculumOverviewComponent, "ELBH121");
        } finally {
            tester.isGoingTo("enable the curriculum code");
            await db.updateInDatabase("curriculum_node", { enabled: 1 }, { id: CURRICULUM_NODE_ACHHK078 });
        }
    });

    test.afterAll(async ({ api, baseTester, db }) => {
        baseTester.isGoingTo("clean up Curriculum Overview component");
        await db.dontHaveInDatabase("curriculum_node_for_category", { category_id: HOMEPAGE_ID });
        if (curriculumOverviewComponentId !== null) {
            await api.deleteHomepageComponent(HOMEPAGE_ID, curriculumOverviewComponentId);
        }
    });
});
