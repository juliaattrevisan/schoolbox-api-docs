/**
 * Manage the currently active curriculum node.
 * An 'active' node is toggled when we choose to
 * update or add a subsection to an existing node
 * or view it usage details and description
 *
 * This is different to a 'selected' node handled with mcSelectedNodesStore,
 * which is used to bulk enable or delete MULTIPLE nodes
 */

/* eslint-disable import/no-cycle -- Disable dependency cycle warning temporarily [SA-18428] */

import { defineStore } from "pinia";
import { useNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/store/mcNodesStore";
import { UNSET_ID, getEmptyNode, sortNodes } from "@schoolboxCurriculum/js/nodeUtils";
import { useViewNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/viewNodes/store/mcViewNodesStore";
import type { Node, NodeToUpdate } from "@schoolboxCurriculum/types/Node";

export const useActiveNodeStore = defineStore("mcActiveNode", () => {
    const node = window.Vue.ref<Node>(getEmptyNode()); // the currently selected node

    /**
     * To show further info on a selected node
     * @returns { boolean }
     */
    const isActive = window.Vue.computed(() => node.value.id !== UNSET_ID);

    /**
     * Add in the code if it's available otherwise just show the title
     * @returns { string }
     */
    const title = window.Vue.computed(() => {
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
     * @param { Node } newNode - the new node
     */
    function set(newNode: Node) {
        node.value = newNode;
    }

    /**
     * Set newly saved values on the node
     * @param { NodeToUpdate } newNode - the new node
     */
    function update(newNode: NodeToUpdate) {
        if (newNode.name !== node.value.name) {
            node.value.name = newNode.name.trim();
        }
        if (typeof newNode.code === "string" && newNode.code !== node.value.code) {
            node.value.code = newNode.code.trim();
        }
        if (newNode.description !== node.value.description) {
            node.value.description = newNode.description;
        }
        useNodesStore().nodeUpdated(node.value); // notify to prompt a sort
    }

    /**
     * Add in a new subsection to the active node
     * @param { Node } newNode - the new node to add in
     */
    async function addSubsection(newNode: Node) {
        if (node.value.children === null) {
            // if the children aren't loaded, load them in - which will also return the newly added node
            await useNodesStore().loadNextChildren(node.value.id);
        } else {
            // otherwise we need to push the newNode in as the children are already loaded
            node.value.children.push(newNode);
            node.value.children = sortNodes(node.value.children); // and sort again with the new node added
        }
        const viewNodesStore = useViewNodesStore();
        if (viewNodesStore.current.id !== newNode.parent?.id) {
            // if we aren't on the new nodes level move down so we can see the new node
            await viewNodesStore.setNextSelected(newNode.parent?.id as number);
        }
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
        update,
        addSubsection,
        unset,
    };
});
