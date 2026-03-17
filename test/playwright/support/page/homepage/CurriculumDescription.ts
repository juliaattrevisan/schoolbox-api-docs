import { expect } from "@playwright/test";
import type { Locator, Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class CurriculumDescription {
    private tester: AcceptanceTester;
    private page: Page;

    static readonly ELEMENT_TITLE_TEXTBOX = 'input[name="componentname"]';
    static readonly ELEMENT_UPDATE_BUTTON = 'input[value="Update"]';
    static readonly ELEMENT_CURRICULUM_CODE_DROPDOWN = 'select[name="curriculumCode"]';
    static readonly DATA_TEST_CURRICULUM_CODE = "curriculum-code-{curriculumCode}";
    static readonly DATA_TEST_CURRICULUM_DESCRIPTION = "curriculum-description-{curriculumCode}";


    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Fill the component title field
     * @param curriculumDescriptionComponent - The curriculum description component locator
     * @param title - The title to enter
     */

    public async addTitle(curriculumDescriptionComponent: Locator, title: string): Promise<void> {
        this.tester.isGoingTo("Add a title");
        await curriculumDescriptionComponent.locator(CurriculumDescription.ELEMENT_TITLE_TEXTBOX).fill(title);
    }

    /**
     * Select a curriculum code from the dropdown and click Update
     * @param curriculumDescriptionComponent - The curriculum description component locator
     * @param curriculumCode - The curriculum code to select
     */

    public static async setCurriculumCode(curriculumDescriptionComponent: Locator, curriculumCode: string,): Promise<void> {
        await curriculumDescriptionComponent
            .locator(CurriculumDescription.ELEMENT_CURRICULUM_CODE_DROPDOWN)
            .selectOption(curriculumCode);
        await curriculumDescriptionComponent.locator(CurriculumDescription.ELEMENT_UPDATE_BUTTON).click();
    }

    /**
     * Verify if a curriculum code link is present in the component
     * @param curriculumDescriptionComponent - The curriculum description component locator
     * @param code - The curriculum code to verify (e.g. ACELA1556)
     */
    public static async verifyCurriculumLinkCode(curriculumDescriptionComponent: Locator, code: string): Promise<void> {
        const codeElement = curriculumDescriptionComponent.getByTestId(
            CurriculumDescription.DATA_TEST_CURRICULUM_CODE.replace("{curriculumCode}", code),
        );
        await expect(codeElement).toBeVisible();
        await expect(codeElement).toContainText(code);
    }

    /**
     * Verify if a curriculum row (code + description) is present in the component
     * @param curriculumDescriptionComponent - The curriculum description component locator
     * @param descriptionContains - The description text to verify (e.g. "Use and influence of language")  
     */
    public static async verifyCurriculumDescriptionRow(
        curriculumDescriptionComponent: Locator,
        code: string,
        description: string,
    ): Promise<void> {
        const descriptionRow = curriculumDescriptionComponent.getByTestId(CurriculumDescription.DATA_TEST_CURRICULUM_DESCRIPTION.replace("{curriculumCode}", code,),);
        await expect(descriptionRow).toBeVisible();
        await expect(descriptionRow).toContainText(description);
    }

    /**
     * Click a curriculum code link in the component
     * @param curriculumDescriptionComponent - The curriculum description component locator
     * @param code - The curriculum code to click (e.g. ACELA1556)
     */
    public async clickCurriculumCodeLink(
        curriculumDescriptionComponent: Locator,
        code: string,
    ): Promise<void> {
        const codeLink = curriculumDescriptionComponent.getByTestId(CurriculumDescription.DATA_TEST_CURRICULUM_CODE.replace("{curriculumCode}", code),);
        await expect(codeLink).toBeVisible();
        await codeLink.click();
    }
}
