/**
 * To manage curriculum related state within the unit planner,
 * handling the curriculum selector modal and its interactions with the current folderNodesStore
 */

import { defineStore } from "pinia";
import { watchIgnorable } from "@vueuse/core";
import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import { useStore as useSelectorStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/curriculumSelectorStore";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csSelectedNodesStore";
import { useStore as useFolderNodesStore } from "@schoolboxCurriculum/vue/folderNodes/store/folderNodesStore";
import { useModalStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csModalStore";
import type { CurriculumOverviewComponent } from "../unitPlannerTypes";
import type { Column } from "../unitPlannerConfig";

export const useUnitPlannerCurriculumStore = defineStore(`unitPlannerCurriculum`, () => {
    const selectorStore = useSelectorStore(); // for the curriculum selector modal
    const selectedNodesStore = useSelectedNodesStore(); // to update the selectedNodes linked to the curriculum selector modal
    const modalStore = useModalStore(); // to open the modal
    const startingNodeId = window.Vue.ref<number | undefined>(undefined); // to open the modal at a specific node
    const selectedDetailsNodeId = window.Vue.ref<number>(UNSET_ID); // when changed will trigger the curriculum-details-modal to open
    let currentFolderId: number | null = null; // to target the appropriate folderNodesStore.selectedNodes when updating via the curriculum modal
    let currentComponentId: number | null = null; // to target the appropriate folderNodesStore.selectedNodes when updating via the curriculum modal

    /**
     * Initialises the curriculum selector modal store, which loads the initial curriculum data
     */
    function init(): void {
        selectorStore.init({
            isReadOnly: false,
            formValueName: "", // not required in this context
            dataEndpoint: "/api/curriculum",
            selectedNodes: [], // so the modal displays the latest selected nodes
        });
    }

    /**
     * Changes have been made to the selected nodes via the selector modal,
     * so update the current folder nodes store
     */
    const { ignoreUpdates } = watchIgnorable(
        () => selectedNodesStore.nodes,
        () => {
            // target the correct store to update the nodes
            const folderNodesStore = useFolderNodesStore(currentFolderId!, currentComponentId!);
            void folderNodesStore().updateNodes(selectedNodesStore.nodes);
        },
    );

    /**
     * To open the curriculum selector modal, targeting the relevant folderNodesStore
     * @param {number} folderId - the folderId to target
     * @param {number} componentId - the componentId to target
     */
    function openCurriculumSelectorModal(folderId: number, componentId: number): void {
        currentFolderId = folderId;
        currentComponentId = componentId;
        const folderNodesStore = useFolderNodesStore(folderId, componentId);
        ignoreUpdates(() => {
            // so the watch above doesn't trigger, setting off an infinite loop
            selectedNodesStore.update(folderNodesStore().selectedNodes); // update to latest selected nodes prior to opening the modal
        });
        startingNodeId.value = folderNodesStore().filter ? folderNodesStore().filter?.id : undefined;
        modalStore.open();
    }

    /**
     * To retrieve the curriculum component id for the column if it's available,
     * so we can retrieve the correct folderNodesStore and load in the appropriate node tree
     * @param {CurriculumOverviewComponent[]} components - the components on the unit
     * @param {Column} column - the column to match
     * @returns {CurriculumOverviewComponent|null} - the component found or null
     */
    function getCurriculumComponent(components: CurriculumOverviewComponent[], column: Column) {
        // loop through the curriculum components on the unit, matching the component heading to the column label
        const matchedComponent = components.find(
            (component) => component.heading.trim() === column.label.trim(), // Ignore trailing or leading whitespace
        );
        if (matchedComponent) {
            return matchedComponent;
        }
        return null;
    }

    return {
        init,
        selectorStore,
        startingNodeId,
        selectedDetailsNodeId,
        openCurriculumSelectorModal,
        getCurriculumComponent,
    };
});
