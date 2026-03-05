import { expect } from "@playwright/test";
import type { Locator, Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class CurriculumOverview {
    private tester: AcceptanceTester;
    private page: Page;

    static readonly ELEMENT_TITLE_TEXTBOX = 'input[name="componentname"]';
    static readonly ELEMENT_UPDATE_BUTTON = 'input[value="Update"]';
    static readonly ELEMENT_CURRICULUM_CODE_DROPDOWN = 'select[name="curriculumCode"]';
    static readonly ELEMENT_CURRICULUM_CODE_LINKS = '[class="information-list"] li.content p.no-margin a[title="View in Manage Curriculum"]';
    static readonly ELEMENT_CURRICULUM_ROWS_WITH_DESCRIPTION = '[class="information-list"] li.content p.no-margin';

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     *
     * @param curriculumOverviewComponent - The curriculum overview component
     * @param title - The title of the link
     */

    public async addTitle(curriculumOverviewComponent: Locator, title: string,): Promise<void> {
        this.tester.isGoingTo("Add a title and click Update");
        await curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_TITLE_TEXTBOX).fill(title);
    }


    /**
     *
     * @param curriculumOverviewComponent - The curriculum overview component
     * @param curriculumCode - The curriculum code to set
     */

    public static async setLinkLimit(curriculumOverviewComponent: Locator, curriculumCode: string,): Promise<void> {
        await curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_CURRICULUM_CODE_DROPDOWN).selectOption(curriculumCode);
        await curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_UPDATE_BUTTON).click();
    }
    /**
     * Verify if a curriculum code link is present in the component
     * @param curriculumOverviewComponent - The curriculum overview component locator
     * @param code - The curriculum code (e.g. ACELA1556)
     */
    public static async verifyCurriculumLinkCode(curriculumOverviewComponent: Locator, code: string,): Promise<void> {
        const actualCodes = (await curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_CURRICULUM_CODE_LINKS).allTextContents()).map(text => text.replace(/\s+/g, " ").trim());
        console.log("Expected code:", code);
        console.log("Actual curriculum codes:", actualCodes);

        await expect(curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_CURRICULUM_CODE_LINKS, { hasText: code },),).toBeVisible();
        await expect(curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_CURRICULUM_CODE_LINKS, { hasText: code },),).toContainText(code);
    }

    /**
     * Verify if a curriculum row (code + description) is present in the component
     * @param curriculumOverviewComponent - The curriculum overview component locator
     * @param code - The curriculum code (e.g. ACELA1556)
     * @param descriptionContains - any text you expect in the description
     */
    public static async verifyCurriculumCodeWithDescription(curriculumOverviewComponent: Locator, descriptionContains: string,): Promise<void> {
        const actualRows = (await curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_CURRICULUM_ROWS_WITH_DESCRIPTION).allTextContents()).map(text => text.replace(/\s+/g, " ").trim());
        console.log("Expected description:", descriptionContains);
        console.log("Actual curriculum rows:", actualRows);
        await expect(curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_CURRICULUM_ROWS_WITH_DESCRIPTION, { hasText: descriptionContains },),).toBeVisible();
        await expect(curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_CURRICULUM_ROWS_WITH_DESCRIPTION, { hasText: descriptionContains },),).toContainText(descriptionContains);


    }
    /**
         * Verify if a curriculum row (code + description) is present in the component
         * @param curriculumOverviewComponent - The curriculum overview component locator
         * @param code - The curriculum code (e.g. ACELA1556)
         */

    public async clickCurriculumCodeLink(curriculumOverviewComponent: Locator, code: string,
    ): Promise<void> {
        await curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_CURRICULUM_CODE_LINKS, { hasText: code }).click();
    }








}