/**
 * Manages the currently visible nodes in mc-view-nodes
 * those filtered by both <select> and also the search <input>
 */

import { defineStore } from "pinia";
import type { Node } from "@schoolboxCurriculum/types/Node";

export const useStore = defineStore("overviewComponentStore", () => {
    const nodes = window.Vue.ref<Node[]>([]); // the current nodes to display the entire tree of

    return {
        nodes,
    };
});
