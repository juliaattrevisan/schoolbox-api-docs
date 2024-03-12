/**
 * Manages the currently visible nodes in mc-view-nodes
 * those filtered by both <select> and also the search <input>
 */

import { defineStore } from "pinia";
import type { Node } from "@schoolboxCurriculum/types/Node";

export const useStore = defineStore("overviewComponentStore", () => {
    const nodes = window.Vue.ref<Node[]>([]); // the current nodes to display the entire tree of
    const hasNoNodes = window.Vue.computed(() => nodes.value.length === 0);
    const selectedDetailsNodeId = window.Vue.ref<number | null>(null); // the currently selected node id to display the details of

    /**
     * set up the passed properties and start loading the node data
     * @param {Node[]} $nodes - the nodes to display
     */
    function init($nodes: Node[]): void {
        nodes.value = $nodes;
    }

    return {
        nodes,
        hasNoNodes,
        selectedDetailsNodeId,
        init,
    };
});
