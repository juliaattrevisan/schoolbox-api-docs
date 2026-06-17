import { expect } from "@playwright/test";
import type { Locator } from "@playwright/test";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";

export default class CurriculumDescription {
    static readonly ELEMENT_CURRICULUM_CODE_DROPDOWN = 'select[name="curriculumCode"]';
    static readonly DATA_TEST_CURRICULUM_CODE = "curriculum-code-{curriculumCode}";
    static readonly DATA_TEST_CURRICULUM_DESCRIPTION = "curriculum-description-{curriculumCode}";

    /**
     * Select a curriculum code from the dropdown and click Update
     * @param curriculumDescriptionComponent - The curriculum description component locator
     * @param curriculumCode - The curriculum code to select
     */

    public static async setCurriculumCode(
        curriculumDescriptionComponent: Locator,
        curriculumCode: string,
    ): Promise<void> {
        await curriculumDescriptionComponent
            .locator(CurriculumDescription.ELEMENT_CURRICULUM_CODE_DROPDOWN)
            .selectOption(curriculumCode);
        await Homepage.clickComponentUpdateButton(curriculumDescriptionComponent);
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
     * Verify a curriculum description is visible in the component
     * @param curriculumDescriptionComponent - The curriculum description component locator
     * @param code - The curriculum code for the description row
     * @param description - The description text to verify
     */
    public static async verifyCurriculumDescriptionRow(
        curriculumDescriptionComponent: Locator,
        code: string,
        description: string,
    ): Promise<void> {
        const descriptionRow = curriculumDescriptionComponent.getByTestId(
            CurriculumDescription.DATA_TEST_CURRICULUM_DESCRIPTION.replace("{curriculumCode}", code),
        );
        await expect(descriptionRow).toBeVisible();
        await expect(descriptionRow).toContainText(description);
    }

    /**
     * Click a curriculum code link in the component
     * @param curriculumDescriptionComponent - The curriculum description component locator
     * @param code - The curriculum code to click (e.g. ACELA1556)
     */
    public static async clickCurriculumCodeLink(curriculumDescriptionComponent: Locator, code: string): Promise<void> {
        const codeLink = curriculumDescriptionComponent.getByTestId(
            CurriculumDescription.DATA_TEST_CURRICULUM_CODE.replace("{curriculumCode}", code),
        );
        await expect(codeLink).toBeVisible();
        await codeLink.click();
    }
}
