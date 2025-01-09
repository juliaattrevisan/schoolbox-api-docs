/**
 * To manage curriculum related state within the unit planner,
 * handling the curriculum selector modal and its interactions with the current folderNodesStore
 */

import { defineStore } from "pinia";
import { watchIgnorable } from "@vueuse/core";
import { activate, timeout } from "@schoolboxVue/components/toast/sbxToastEvent";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import { useStore as useSelectorStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/curriculumSelectorStore";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csSelectedNodesStore";
import { useStore as useFolderNodesStore } from "@schoolboxCurriculum/vue/folderNodes/store/folderNodesStore";
import { useModalStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csModalStore";
import { useUnitPlannerStore } from "./unitPlannerStore";
import type { CurriculumOverviewComponent, Unit } from "../unitPlannerTypes";
import type { Column } from "../unitPlannerConfig";

export const useUnitPlannerCurriculumStore = defineStore(`unitPlannerCurriculum`, () => {
    const selectorStore = useSelectorStore(); // for the curriculum selector modal
    const selectedNodesStore = useSelectedNodesStore(); // to update the selectedNodes linked to the curriculum selector modal
    const modalStore = useModalStore(); // to open the modal
    const startingNodeId = window.Vue.ref<number>(UNSET_ID); // to open the modal at a specific node
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
            includeChildren: true, // include all children in selections
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
     * @param folderId - the folderId to target
     * @param componentId - the componentId to target
     */
    function openCurriculumSelectorModal(folderId: number, componentId: number): void {
        const folderNodesStore = useFolderNodesStore(folderId, componentId);
        ignoreUpdates(() => {
            // so the watch above doesn't trigger, setting off an infinite loop
            selectedNodesStore.update(folderNodesStore().selectedNodes); // update to latest selected nodes prior to opening the modal
        });
        // if we are just opening up the same modal, just open it
        if (folderId === currentFolderId && componentId === currentComponentId) {
            modalStore.open();
            return;
        }
        currentFolderId = folderId;
        currentComponentId = componentId;
        // if the Curriculum Tree Filter is set get it to open the modal at the correct node, otherwise open at the root
        const { filter } = folderNodesStore();
        startingNodeId.value = filter !== null ? filter.id : UNSET_ID;
        modalStore.toStartingNodeId(startingNodeId.value);
        modalStore.open();
    }

    /**
     * To retrieve the curriculum component id for the column if it's available,
     * so we can retrieve the correct folderNodesStore and load in the appropriate node tree
     * @param components - the components on the unit
     * @param column - the column to match
     * @returns - the component found or null
     */
    function getComponent(
        components: CurriculumOverviewComponent[],
        column: Column,
    ): CurriculumOverviewComponent | null {
        // loop through the curriculum components on the unit, matching the component heading to the column label
        const matchedComponent = components.find(
            (component) => component.heading.trim() === column.label.trim(), // Ignore trailing or leading whitespace
        );
        if (matchedComponent) {
            return matchedComponent;
        }
        return null;
    }

    /**
     * Create a new curriculum overview component for the unit,
     * placing the component on units homepage
     * @param unit - the unit to create the component for
     * @param column - the column to create the component for
     */
    async function createComponent(unit: Unit, column: Column): Promise<void> {
        const unitPlannerStore = useUnitPlannerStore();
        const componentDefinition = unitPlannerStore.template?.curriculumDefId ?? null;

        if (componentDefinition === null) {
            throw new Error("No Curriculum components exist on the template,");
        }

        activate("Creating curriculum...");
        try {
            // First create a new component
            const createFormData = new FormData();
            createFormData.append("cid", unit.folderId.toString());
            createFormData.append("add", componentDefinition.toString());

            const createResponse = await fetch(`${unit.curriculum._routes.createComponent}`, {
                method: "POST",
                body: createFormData,
                headers: {
                    Accept: "application/json",
                },
            });

            if (!createResponse.ok) {
                throw new Error(await extractErrorMessage(createResponse));
            }

            const createResponseJSON = (await createResponse.json()) as { instanceID: number };

            // get the filter id for the component if it's available based on matching the component heading to column label
            // to open the modal at the correct node
            const treeFilter = unitPlannerStore.template?.curriculum.find(
                (component) => component.heading === column.label,
            )?.filter;

            const configSearchParams = new FormData();
            configSearchParams.append("cid", unit.folderId.toString());
            configSearchParams.append("type", "config");
            configSearchParams.append("component", createResponseJSON.instanceID.toString());
            configSearchParams.append("componentname", column.label);
            configSearchParams.append("curriculumTreeFilter", treeFilter ? treeFilter.id.toString() : "");

            // Then create the component
            const configResponse = await fetch(`${unit.curriculum._routes.createComponent}`, {
                method: "POST",
                body: configSearchParams,
                headers: {
                    Accept: "application/json",
                },
            });

            if (!configResponse.ok) {
                throw new Error(await extractErrorMessage(configResponse));
            }

            const newComponent: CurriculumOverviewComponent = {
                componentId: createResponseJSON.instanceID,
                heading: column.label,
                filter: treeFilter,
                inTemplate: true,
            };
            unitPlannerStore.addCurriculumComponent(unit.id, newComponent);

            timeout({ text: "Curriculum created successfully" });
        } catch (error) {
            timeout({ text: error as string, isSuccess: false });
        }
    }

    return {
        init,
        selectorStore,
        startingNodeId,
        selectedDetailsNodeId,
        openCurriculumSelectorModal,
        getComponent,
        createComponent,
    };
});
