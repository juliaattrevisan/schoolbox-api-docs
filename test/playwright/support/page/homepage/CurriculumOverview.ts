import { expect } from "@playwright/test";
import type { Locator, Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import CurriculumSelector from "@schoolboxTest/playwright/support/page/curriculum/selector/CurriculumSelector";

export default class CurriculumOverview {
    private tester: AcceptanceTester;
    private page: Page;

    static readonly DATA_TEST_EMPTY_STATE_MAP_CURRICULUM_CODES = "map-curriculum-codes";
    static readonly DATA_TEST_FN_LIST_CONTAINER = "fn-list-container";
    static readonly DATA_TEST_MODIFY_CURRICULUM = "unit-planner-curriculum-modify-{folderId}";
    static readonly ELEMENT_DETAILS_MODAL = ".reveal-modal.open";
    static readonly ELEMENT_DETAILS_MODAL_TITLE = `${CurriculumOverview.ELEMENT_DETAILS_MODAL} h2`;
    static readonly ELEMENT_DETAILS_MODAL_BREADCRUMB = `${CurriculumOverview.ELEMENT_DETAILS_MODAL} p.meta`;

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Clicks the Map curriculum codes link in the empty state to open the curriculum selector modal.
     */
    async clickMapCurriculumCodes(): Promise<void> {
        await this.page.getByTestId(CurriculumOverview.DATA_TEST_EMPTY_STATE_MAP_CURRICULUM_CODES).click();
    }

    /**
     * Map curriculum codes via the Curriculum Overview component.
     * @param component - The Curriculum Overview component locator
     * @param folderId - The homepage folder ID
     * @param pathIds - Curriculum tree path to browse
     * @param nodeIds - Curriculum nodes to select
     */
    public async mapCurriculumCodes(
        component: Locator,
        folderId: number,
        pathIds: number[],
        nodeIds: number[],
    ): Promise<void> {
        const curriculumSelector = new CurriculumSelector(this.tester, this.page);
        const modifyButton = component.getByTestId(
            CurriculumOverview.DATA_TEST_MODIFY_CURRICULUM.replace("{folderId}", folderId.toString()),
        );

        if (await modifyButton.isVisible()) {
            await modifyButton.click();
        } else {
            await component.getByTestId(CurriculumOverview.DATA_TEST_EMPTY_STATE_MAP_CURRICULUM_CODES).click();
        }

        await curriculumSelector.browseToNode(pathIds);
        await curriculumSelector.selectNodes(nodeIds);
        await curriculumSelector.saveModal();
    }

    /**
     * Verify the overview list contains text.
     * @param component - The Curriculum Overview component locator
     * @param text - Expected text
     */
    public static async expectListToContainText(component: Locator, text: string): Promise<void> {
        await expect(component.getByTestId(CurriculumOverview.DATA_TEST_FN_LIST_CONTAINER)).toContainText(text);
    }

    /**
     * Verify the overview list does not contain text.
     * @param component - The Curriculum Overview component locator
     * @param text - Text that should not be present
     */
    public static async expectListNotToContainText(component: Locator, text: string): Promise<void> {
        await expect(component.getByTestId(CurriculumOverview.DATA_TEST_FN_LIST_CONTAINER)).not.toContainText(text);
    }

    /**
     * Verify the disabled icon is visible for a curriculum node.
     * @param component - The Curriculum Overview component locator
     * @param nodeId - Curriculum node ID
     */
    public static async expectDisabledIconToBeVisible(component: Locator, nodeId: number): Promise<void> {
        await expect(component.getByTestId(`fn-list-node-disabled-${nodeId}`)).toBeVisible();
    }

    /**
     * Verify a curriculum node link is not visible in the overview list.
     * @param component - The Curriculum Overview component locator
     * @param nodeId - Curriculum node ID
     */
    public static async expectCurriculumCodeNotToBeVisible(component: Locator, nodeId: number): Promise<void> {
        await expect(
            component.getByTestId(CurriculumOverview.DATA_TEST_FN_LIST_CONTAINER).locator(`a[href="#${nodeId}"]`),
        ).not.toBeVisible();
    }

    /**
     * Click a mapped curriculum code in the overview list.
     * @param component - The Curriculum Overview component locator
     * @param nodeId - Curriculum node ID
     */
    public static async clickCurriculumCode(component: Locator, nodeId: number): Promise<void> {
        await component
            .getByTestId(CurriculumOverview.DATA_TEST_FN_LIST_CONTAINER)
            .locator(`a[href="#${nodeId}"]`)
            .click();
    }

    /**
     * Verify the curriculum details modal title.
     * @param title - Expected title text
     */
    public async expectDetailsModalTitle(title: string): Promise<void> {
        await expect(this.page.locator(CurriculumOverview.ELEMENT_DETAILS_MODAL_TITLE)).toHaveText(title);
    }

    /**
     * Verify the curriculum details modal breadcrumb.
     * @param breadcrumb - Expected breadcrumb text
     */
    public async expectDetailsModalBreadcrumb(breadcrumb: string): Promise<void> {
        await expect(this.page.locator(CurriculumOverview.ELEMENT_DETAILS_MODAL_BREADCRUMB)).toHaveText(breadcrumb);
    }
}
