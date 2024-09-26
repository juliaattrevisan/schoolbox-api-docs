import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { Page } from "@playwright/test";

export default class LessonPlanTemplateList {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/adminv2/lesson-plan/template";

    static readonly ICON_ADD = "a.icon-add";

    static readonly ICON_MODAL_CLOSE = "a.close-reveal-modal";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to the learning badges page
     */
    async goto(): Promise<void> {
        await this.page.goto(LessonPlanTemplateList.URL);
    }

    /**
     * click add lesson plan template icon
     */
    async clickAddLessonPlanTemplateButton(): Promise<void> {
        await this.page.waitForSelector(LessonPlanTemplateList.ICON_ADD);
        await this.page.click(LessonPlanTemplateList.ICON_ADD);
    }

    /**
     * click modify lesson plan template icon by passing the lesson plan template id
     * @param templateIdNumber - the number of the template
     */
    async clickModifyLessonPlanTemplateButton(templateIdNumber: number): Promise<void> {
        await this.page.click(`a[href="/adminv2/lesson-plan/template/update/${templateIdNumber}"]`);
    }

    /**
     * click the lesson plan template title by passing the lesson plan template id
     * @param templateIdNumber - the number of the template
     */
    async clickLessonPlanTemplateTitle(templateIdNumber: number): Promise<void> {
        await this.page.click(`a[data-reveal-id="lpt-preview-${templateIdNumber}"]`);
    }

    /**
     * click 3-dot button for a lesson plan template by passing the lesson plan template id
     * @param templateIdNumber - the number of the template
     */
    async clickLessonPlanTemplateMenu(templateIdNumber: number): Promise<void> {
        await this.page.getByTestId(`sbx-pop-out-lpt-${templateIdNumber}-button`).click();
    }

    /**
     * click disable/enable lesson plan on the 3-dot menu dropdown
     * @param templateIdNumber  - the number of the template
     */
    async clickDisableEnableVisibility(templateIdNumber: number): Promise<void> {
        await this.page.click(`a[href="/adminv2/lesson-plan/template/toggle/${templateIdNumber}"]`);
    }

    /**
     * click delete lesson plan on the 3-dot menu dropdown
     * @param templateIdNumber - the id of the template
     */
    async clickDeleteTemplate(templateIdNumber: number): Promise<void> {
        await this.page.click(`a[href="/adminv2/lesson-plan/template/delete/${templateIdNumber}"]`);
    }

    /**
     * click the close button on the modal dialog
     * @param templateIdNumber - the id of the template
     */
    async clickCloseModal(templateIdNumber: number): Promise<void> {
        await this.page.waitForTimeout(1000);
        await this.page.getByTestId(`lpt-preview-${templateIdNumber}-template-list-modal-close`).click();
    }
}
