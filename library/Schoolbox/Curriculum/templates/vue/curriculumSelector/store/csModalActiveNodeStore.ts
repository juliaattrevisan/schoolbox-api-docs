/**
 * Manage the currently active curriculum node.
 * An 'active' node is toggled when we choose to
 * update or add a subsection to an existing node.
 *
 * This is different to a 'selected' node handled with mcSelectedNodesStore,
 * which is used to bulk enable or delete MULTIPLE nodes
 */

import { ref, computed } from "vue";
import { defineStore } from "pinia";
import { UNSET_ID, getEmptyNode } from "@schoolboxCurriculum/js/nodeUtils";
import type { Node } from "@schoolboxCurriculum/types/Node";

export const useModalActiveNodeStore = defineStore("mcModalActiveNode", () => {
    const node = ref<Node>(getEmptyNode()); // the currently selected node

    /**
     * To show further info on a selected node
     *
     * @returns { boolean }
     */
    const isActive = computed(() => node.value.id !== UNSET_ID);

    /**
     * Add in the code if it's available otherwise just show the title
     *
     * @returns { string }
     */
    const title = computed(() => {
        if (node.value.id === UNSET_ID) {
            return "";
        }
        if (node.value.code && node.value.code !== "") {
            return `[${node.value.code}] ${node.value.name}`;
        }
        return node.value.name;
    });

    /**
     * set the node to perform an action on
     *
     * @param { Node } newNode - the new node
     */
    function set(newNode: Node) {
        node.value = newNode;
    }

    /**
     * unset ready for another use
     */
    function unset() {
        node.value = getEmptyNode();
    }

    return {
        node,
        isActive,
        title,
        set,
        unset,
    };
});
