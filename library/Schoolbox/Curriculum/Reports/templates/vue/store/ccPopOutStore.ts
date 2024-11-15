/**
 * Manage the loading and state of the single cc-usage-pop-out
 */

import { defineStore } from "pinia";
import type { CurriculumCoverageNode } from "../../js/types/curriculumCoverageTypes";

export const usePopOutStore = defineStore(`ccPopOut`, () => {
    const selectedNode = window.Vue.ref<CurriculumCoverageNode | null>(null);

    /**
     * report has been triggered so set the active course and nodes
     * @param node - the node to set as active
     */
    function setActive(node: CurriculumCoverageNode): void {
        selectedNode.value = node;
    }

    return {
        selectedNode,
        setActive,
    };
});
