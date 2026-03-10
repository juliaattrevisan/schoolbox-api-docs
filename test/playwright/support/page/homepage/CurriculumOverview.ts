import { expect } from "@playwright/test";
import type { Locator, Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class CurriculumOverview {
    private tester: AcceptanceTester;
    private page: Page;

    static readonly ELEMENT_TITLE_TEXTBOX = 'input[name="componentname"]';
    static readonly ELEMENT_UPDATE_BUTTON = 'input[value="Update"]';
    static readonly ELEMENT_CURRICULUM_CODE_DROPDOWN = 'select[name="curriculumCode"]';
    static readonly ELEMENT_CURRICULUM_CODE_LINKS = 'a[title="View in Manage Curriculum"]';
    static readonly ELEMENT_CURRICULUM_DESCRIPTION_ROWS = 'p:has(a[title="View in Manage Curriculum"])';

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Fill the component title field
     * @param curriculumOverviewComponent - The curriculum overview component locator
     * @param title - The component title
     */

    public async addTitle(curriculumOverviewComponent: Locator, title: string,): Promise<void> {
        this.tester.isGoingTo("Add a title");
        await curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_TITLE_TEXTBOX).fill(title);
    }

    /**
     * Select a curriculum code from the dropdown and click Update
     * @param curriculumOverviewComponent - The curriculum overview component locator
     * @param curriculumCode - The curriculum code to select
     */

    public static async setCurriculumCode(curriculumOverviewComponent: Locator, curriculumCode: string,): Promise<void> {
        await curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_CURRICULUM_CODE_DROPDOWN).selectOption(curriculumCode);
        await curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_UPDATE_BUTTON).click();
    }

    /**
     * Verify if a curriculum code link is present in the component
     * @param curriculumOverviewComponent - The curriculum overview component locator
     * @param code - The curriculum code (e.g. ACELA1556)
     */
    public static async verifyCurriculumLinkCode(curriculumOverviewComponent: Locator, code: string,): Promise<void> {

        const codeLink = curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_CURRICULUM_CODE_LINKS, { hasText: code },);
        const actualCodes = (await curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_CURRICULUM_CODE_LINKS).allTextContents()).map(text => text.replace(/\s+/g, " ").trim());
        console.log("Expected code:", code);
        console.log("Actual curriculum codes:", actualCodes);
        await expect(codeLink).toBeVisible();
        await expect(codeLink).toContainText(code);
    }

    /**
     * Verify if a curriculum row (code + description) is present in the component
     * @param curriculumOverviewComponent - The curriculum overview component locator
     * @param descriptionContains - any text you expect in the description
     */
    public static async verifyCurriculumDescriptionRow(curriculumOverviewComponent: Locator, descriptionContains: string,): Promise<void> {
        const descriptionRow = curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_CURRICULUM_DESCRIPTION_ROWS, { hasText: descriptionContains },);
        const actualRows = (await curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_CURRICULUM_DESCRIPTION_ROWS).allTextContents()).map(text => text.replace(/\s+/g, " ").trim());
        console.log("Expected description:", descriptionContains);
        console.log("Actual curriculum rows:", actualRows);
        await expect(descriptionRow).toBeVisible();
        await expect(descriptionRow).toContainText(descriptionContains);

    }

    /**
         * Verify if a curriculum row (code + description) is present in the component
         * @param curriculumOverviewComponent - The curriculum overview component locator
         * @param code - The curriculum code (e.g. ACELA1556)
         */
    public async clickCurriculumCodeLink(curriculumOverviewComponent: Locator, code: string,): Promise<void> {
        const codeLink = curriculumOverviewComponent.locator(CurriculumOverview.ELEMENT_CURRICULUM_CODE_LINKS, { hasText: code },);
        console.log("Clicking curriculum code:", code);
        await expect(codeLink).toBeVisible();
        await codeLink.click();
    }
}