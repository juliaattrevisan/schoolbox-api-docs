/**
 * To load and display nodes linked specifically to the unit
 */

import { defineStore } from "pinia";
import { getOnlyEnabled, findById } from "@schoolboxCurriculum/js/nodeUtils";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { useSharedStore } from "./csSharedStore";

interface InitProps {
    filter: Node | null; // to only display nodes that are children of this node
    parentFolderId?: number; // the parentFolderId to load the course/unit nodes from
}

export const useCurrentNodesStore = defineStore("csUnitNodesStore", () => {
    const hasLoadError = window.Vue.ref(false);
    const hasLoaded = window.Vue.ref(false); // whether the nodes have been loaded or not
    const parentFolderId = window.Vue.ref<number | undefined>(undefined); // the parentFolderId to load the course/unit nodes from
    const nodes = window.Vue.ref<Node[]>([]);
    const hasNodes = window.Vue.ref(false); // no need to display the tree if there are no nodes
    const hasMultipleParents = window.Vue.ref(false); // whether the nodes come from multiple top level curriculum parents
    const filter = window.Vue.ref<Node | null>({} as Node | null); // to only display nodes that are children of this node

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
    };
});
