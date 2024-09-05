/**
 * Manage the currently active curriculum node.
 * An 'active' node is toggled when we select the node
 * to view it usage details and description
 *
 * This is different to a 'selected' node handled with csModalSelectedNodesStore
 * within the modal, and csSelectedNodesStore outside the modal
 */

import { defineStore } from "pinia";
import { getEmptyNode } from "@schoolboxCurriculum/js/nodeUtils";
import type { Node } from "@schoolboxCurriculum/types/Node";

export const useModalActiveNodeStore = defineStore("mcModalActiveNode", () => {
    const node = window.Vue.ref<Node>(getEmptyNode()); // the currently selected node

    /**
     * set the node to perform an action on
     * @param newNode - the new node
     */
    function set(newNode: Node): void {
        node.value = newNode;
    }

    /**
     * unset ready for another use
     */
    function unset(): void {
        node.value = getEmptyNode();
    }

    return {
        node,
        set,
        unset,
    };
});
