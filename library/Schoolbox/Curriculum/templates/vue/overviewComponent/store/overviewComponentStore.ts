/**
 * Manages the currently visible nodes in mc-view-nodes
 * those filtered by both <select> and also the search <input>
 */

import { defineStore } from "pinia";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csSelectedNodesStore";

export const useStore = defineStore("overviewComponent", () => {
    const nodeTrees = window.Vue.ref<Node[]>([]); // the current nodes to display the entire tree of
    const selectedNodes = window.Vue.ref<Node[]>([]); // the currently selected nodes (just those that are selected, not the children within)
    const hasNoNodes = window.Vue.computed(() => nodeTrees.value.length === 0);
    const selectedDetailsNodeId = window.Vue.ref<number>(UNSET_ID); // the currently selected node id to display the details of
    const selectedNodesStore = useSelectedNodesStore();

    /**
     * to open the modal when requested
     */
    window.Vue.watch(
        () => selectedNodesStore.nodes,
        () => {
            // notify the backend of the latest selected nodes, updating the nodeTree with the returned data
        },
    );

    /**
     * set up the passed properties and start loading the node data
     * @param {Node[]} $nodeTrees - the node trees to display
     * @param {Node[]} $selectedNodes - the nodes selected
     */
    function init($nodeTrees: Node[], $selectedNodes: Node[]): void {
        nodeTrees.value = $nodeTrees;
        selectedNodes.value = $selectedNodes;
    }

    /**
     * To remove a node from the nodeTree, pass through the the selectedNodesStore
     * which will then trigger the watch to update the nodeTree
     * @param {Node} node - the node to remove
     */
    function removeNode(node: Node): void {
        selectedNodesStore.removeNode(node);
    }

    return {
        nodeTrees,
        selectedNodes,
        hasNoNodes,
        selectedDetailsNodeId,
        init,
        removeNode,
    };
});
