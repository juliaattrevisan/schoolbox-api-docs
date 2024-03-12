/**
 * Manages the currently visible nodes in mc-view-nodes
 * those filtered by both <select> and also the search <input>
 */

import { defineStore } from "pinia";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";

export const useStore = defineStore("overviewComponentStore", () => {
    const nodeTrees = window.Vue.ref<Node[]>([]); // the current nodes to display the entire tree of
    const selectedNodes = window.Vue.ref<Node[]>([]); // the currently selected nodes (just those that are selected, not the children within)
    const hasNoNodes = window.Vue.computed(() => nodeTrees.value.length === 0);
    const selectedDetailsNodeId = window.Vue.ref<number>(UNSET_ID); // the currently selected node id to display the details of

    /**
     * set up the passed properties and start loading the node data
     * @param {Node[]} $nodeTrees - the node trees to display
     * @param {Node[]} $selectedNodes - the nodes selected
     */
    function init($nodeTrees: Node[], $selectedNodes: Node[]): void {
        nodeTrees.value = $nodeTrees;
        selectedNodes.value = $selectedNodes;
    }

    return {
        nodeTrees,
        selectedNodes,
        hasNoNodes,
        selectedDetailsNodeId,
        init,
    };
});
