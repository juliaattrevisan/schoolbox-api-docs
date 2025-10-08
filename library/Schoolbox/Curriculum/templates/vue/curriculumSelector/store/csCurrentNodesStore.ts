/**
 * To load and display nodes linked specifically to the unit
 */

import { defineStore } from "pinia";
import { getOnlyEnabled, findById } from "@schoolboxCurriculum/js/nodeUtils";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { useSharedStore } from "./csSharedStore";

type InitProps = {
    filter: Node | null; // to only display nodes that are children of this node
    parentFolderId?: number; // the parentFolderId to load the course/unit nodes from
};

type ExpandedState = Pick<Node, "id"> & {
    isExpanded: boolean;
};

export const useCurrentNodesStore = defineStore("csUnitNodesStore", () => {
    const hasLoadError = window.Vue.ref(false);
    const hasLoaded = window.Vue.ref(false); // whether the nodes have been loaded or not
    const parentFolderId = window.Vue.ref<number | undefined>(undefined); // the parentFolderId to load the course/unit nodes from
    const nodes = window.Vue.ref<Node[]>([]);
    const hasNodes = window.Vue.ref(false); // no need to display the tree if there are no nodes
    const hasMultipleParents = window.Vue.ref(false); // whether the nodes come from multiple top level curriculum parents
    const filter = window.Vue.ref<Node | null>({} as Node | null); // to only display nodes that are children of this node
    const expandedStates = window.Vue.ref<ExpandedState[]>([]); // Keep track of whether a node is expanded or not, for when the user switches between the 'Current' and 'All' tabs

    /**
     * We need to filter the nodes to only display the children of the filter node,
     * first checking if the node is a child of the existing node trees
     * and if so only returning the children of the filter node
     * @returns {Node[]} - the nodes that are children of the filter node
     */
    const filteredNodes = window.Vue.computed(() => {
        // Nothing selected? Just show all nodes
        if (filter.value === null) {
            return nodes.value;
        }
        const filterNode = findById(nodes.value, filter.value.id);
        if (filterNode === undefined) {
            // if the filter node isn't within the list of nodes, we'll just return an empty node, highlighting that the filter node doesn't currently contain any selected nodes
            return [];
        }
        return [filterNode];
    });

    /**
     * Pass through the required props to the store
     * @param props - the props to pass through
     */
    function init(props: InitProps): void {
        filter.value = props.filter;
        parentFolderId.value = props.parentFolderId;
    }

    /**
     * load all the nodes linked to a specific course/unit
     */
    async function load(): Promise<void> {
        try {
            const response = await fetch(
                `${useSharedStore().apiEndpoint}/usage/folder/${(parentFolderId.value as number).toString()}?returnFormat=tree&descendants=0`,
                {
                    headers: {
                        "Content-Type": "application/json",
                    },
                },
            );
            if (response.ok) {
                nodes.value = (await response.json()) as Node[];
                nodes.value = getOnlyEnabled(nodes.value);
                if (filteredNodes.value.length > 0) {
                    hasNodes.value = true;
                }
                if (filteredNodes.value.length > 1) {
                    hasMultipleParents.value = true;
                }
            }
        } catch (error) {
            hasLoadError.value = true;
            console.warn("Error loading unit nodes:", error);
        } finally {
            hasLoaded.value = true;
        }
    }

    /**
     * To retrieve the new or previous expanded state of the node
     * @param nodeId - the id of the node to check if it is expanded
     * @returns - whether the node is expanded or not
     */
    function getIsExpanded(nodeId: number): boolean {
        const expandedNode = expandedStates.value.find((node) => node.id === nodeId);
        // if it's undefined, we add the node to the expandedStates array and expand if the store only has one parent
        if (expandedNode === undefined) {
            expandedStates.value.push({ id: nodeId, isExpanded: !hasMultipleParents.value });
        }
        return expandedNode ? expandedNode.isExpanded : !hasMultipleParents.value;
    }

    /**
     * Keep track of whether a node is expanded or not, for when the user switches between the 'Current' and 'All' tabs
     * @param nodeId - the id of the node to check if it is expanded
     * @param $isExpanded - whether the node is expanded or not
     */
    function setIsExpanded(nodeId: number, $isExpanded: boolean): void {
        const index = expandedStates.value.findIndex((node) => node.id === nodeId);
        if (index !== -1) {
            expandedStates.value[index].isExpanded = $isExpanded;
        }
    }

    return {
        hasLoadError,
        hasLoaded,
        parentFolderId,
        nodes,
        filteredNodes,
        hasNodes,
        hasMultipleParents,
        init,
        load,
        getIsExpanded,
        setIsExpanded,
    };
});
