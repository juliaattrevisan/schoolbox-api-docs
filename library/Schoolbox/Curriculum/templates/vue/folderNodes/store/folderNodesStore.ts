/**
 * Manages the currently visible nodes in mc-view-nodes
 * those filtered by both <select> and also the search <input>
 */

import { defineStore } from "pinia";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csSelectedNodesStore";

export const useStore = defineStore("folderNodes", () => {
    const nodes = window.Vue.ref<Node[]>([]); // the current nodes to display the entire tree of
    const hasNoNodes = window.Vue.computed(() => nodes.value.length === 0);
    const selectedDetailsNodeId = window.Vue.ref<number>(UNSET_ID); // the currently selected node id to display the details of
    const selectedNodesStore = useSelectedNodesStore();

    /**
     * to update the backend with the latest selected nodes, so the nodeTree can be updated
     */
    window.Vue.watch(
        () => selectedNodesStore.nodes,
        () => {
            // notify the backend of the latest selected nodes, updating the nodeTree with the returned data
        },
    );

    /**
     * Store a reference to the nodes to display
     * @param {Node[]} $nodes - the node trees to display
     */
    function init($nodes: Node[]): void {
        nodes.value = $nodes;
    }

    /**
     * To remove a node from the nodeTree, pass through the the selectedNodesStore
     * which will then trigger the watch to update the nodeTree
     * @param {Node} node - the node to remove
     */
    function removeSelectedNode(node: Node): void {
        selectedNodesStore.remove(node);
    }

    return {
        nodes,
        hasNoNodes,
        selectedDetailsNodeId,
        selectedNodesStore,
        init,
        removeSelectedNode,
    };
});
