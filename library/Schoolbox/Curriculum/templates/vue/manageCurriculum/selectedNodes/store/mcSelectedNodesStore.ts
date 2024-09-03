/**
 * Manage the currently selected curriculum nodes.
 * A selected node can be bulk enabled or deleted.
 *
 * Note the distinciton with an 'active' node, handled in mcActiveNodeStore
 * which is used to update or add a subsection to a SINGLE node.
 */

/* eslint-disable import/no-cycle -- Disable dependency cycle warning temporarily [SA-18428] */

import { defineStore } from "pinia";
import { useNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/store/mcNodesStore";
import { UNSET_ID, findById, getEmptyNode } from "@schoolboxCurriculum/js/nodeUtils";
import { useEnableNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/selectedNodes/store/mcEnableNodesStore";
import type { Node } from "@schoolboxCurriculum/types/Node";

export const useSelectedNodesStore = defineStore("mcSelectedNodes", () => {
    const nodes = window.Vue.ref<Node[]>([]); // the currently selected nodes
    const childNodes = window.Vue.ref<Node[]>([]); // any children of currently selected nodes
    const activeNode = window.Vue.ref(getEmptyNode()); // to show extra info on a selected code in a modal
    const hasDisabledChildNodes = window.Vue.ref(false);

    /**
     * Do we have any selected nodes?
     * @returns { boolean }
     */
    const hasNoNodes = window.Vue.computed(() => nodes.value.length === 0);

    /**
     * Do we have any selected nodes?
     * @returns { boolean }
     */
    const hasNodes = window.Vue.computed(() => nodes.value.length > 0);

    /**
     * Number of nodes
     * @returns { number }
     */
    const count = window.Vue.computed(() => nodes.value.length);

    /**
     * Return the single or plural depending on node selection
     * @returns { string }
     */
    const singleOrPlural = window.Vue.computed(() => (count.value > 1 ? "outcomes" : "outcome"));

    /**
     * just return an array of ids, used to compare if a node is selected or not
     * @returns { number[] }
     */
    const nodesIds = window.Vue.computed(() => nodes.value.map((node) => node.id));

    /**
     * just return an array of ids, used to compare if a node is selected or not
     * @returns { number[] }
     */
    const childNodesIds = window.Vue.computed(() => childNodes.value.map((node) => node.id));

    /**
     * check if all nodes are enabled so we can just show the disable button
     * @returns { boolean }
     */
    const allNodesEnabled = window.Vue.computed(() => {
        if (hasNodes.value) {
            const enabledNodes = nodes.value.filter((node) => node.enabled);
            return enabledNodes.length === nodes.value.length;
        }
        return false;
    });

    /**
     * check if all nodes are disabled so we can just show the enable button
     * disabled child nodes cant be enabled - only the disabled parent can be enabled
     * @returns { boolean }
     */
    const allNodesDisabled = window.Vue.computed(() => {
        if (hasNodes.value) {
            const enabledNodes = nodes.value.filter((node) => !node.enabled);
            const nodesStore = useNodesStore();
            const noChildEnabledNodes = enabledNodes.filter((node) => {
                // prevent enabling child nodes of disabled parents
                if (node.parent) {
                    // could be the root level node, so won't have a parent
                    const parentNode: Node = findById(nodesStore.nodes.children as Node[], node.parent.id) as Node; // as we checked node.parent, we can be sure parentNode will be found
                    return parentNode.enabled;
                }
                return !node.enabled;
            });
            hasDisabledChildNodes.value = noChildEnabledNodes.length !== enabledNodes.length; // show a note in the the UI that enabling child nodes of disabled parents is not possible
            return noChildEnabledNodes.length === nodes.value.length;
        }
        return false;
    });

    /**
     * To show further info on a selected node
     * @returns { boolean }
     */
    const hasActiveNode = window.Vue.computed(() => activeNode.value.id !== UNSET_ID);

    /**
     * we need to check if a new parent has been selected of any existing children,
     * if it has the children should be removed as the parent now takes precedence
     * @param nodeToAdd - the Node to add
     * @returns - no children of selected nodes
     */
    function noChildrenOfNode(nodeToAdd: Node): Node[] {
        const updatedNodes = nodes.value.filter((selectedNode) => {
            if (nodeToAdd.children) {
                // only if the children have loaded
                const node = findById(nodeToAdd.children, selectedNode.id);
                return node === undefined; // only keep those nodes that aren't a child of the new node
            }
            // otherwise no existing nodes will be children, as they haven't been loaded and so couldn't have been selected
            return true;
        });
        return updatedNodes;
    }

    /**
     * remove any existing child nodes that don't have a .selectedParentId that is an id contained in nodes
     * @returns - the list of Nodes with children removed
     */
    function removeChildNodes(): Node[] {
        return childNodes.value.filter((selectedChildNode) => {
            return nodesIds.value.includes(selectedChildNode.selectedParentId as number);
        });
    }

    /**
     * recursively loop through and update the childNodes array
     * adding any loaded children when the parent node is selected
     * or removing any loaded children from the node just removed
     * @param node - the Node to update
     * @param isSelected - whether the Node is selected or not
     * @param selectedParentId - the id of the parent Node
     */
    function updateChildNodes(node: Node, isSelected: boolean, selectedParentId: number): void {
        if (isSelected) {
            if (!nodesIds.value.includes(node.id) && !childNodesIds.value.includes(node.id)) {
                // don't include the parent node and nodes already in the child list
                node.selectedParentId = selectedParentId;
                childNodes.value = [...childNodes.value, node];
            }
        } else {
            // use the .selectedParentId property to determine which child nodes to remove from the list
            childNodes.value = childNodes.value.filter(
                (selectedCode) => selectedCode.selectedParentId !== selectedParentId,
            );
        }
        node.children?.forEach((innerNode) => {
            updateChildNodes(innerNode, isSelected, selectedParentId);
        });
    }

    /**
     * add a node
     * @param nodeToAdd - the Node to add
     */
    function add(nodeToAdd: Node): void {
        nodes.value = noChildrenOfNode(nodeToAdd);
        nodes.value = [...nodes.value, nodeToAdd];
        childNodes.value = removeChildNodes();
        updateChildNodes(nodeToAdd, true, nodeToAdd.id);
    }

    /**
     * remove a node
     * @param nodeToRemove - the Node to remove
     */
    function remove(nodeToRemove: Node): void {
        updateChildNodes(nodeToRemove, false, nodeToRemove.id);
        nodes.value = nodes.value.filter((node) => node.id !== nodeToRemove.id);
    }

    /**
     * to track whether a node is selected (with mc-view-node-checkbox)
     * for each newly loaded node, we need to check whether its parent has been selected
     * to do this we check if the parent of the loaded node is already in the child nodes list
     * if it is we also need to add in this node
     * @param node - to determine whether the Node is already selected
     */
    function checkIfSelected(node: Node): void {
        const parentNode: Node | undefined = findById(
            useNodesStore().nodes.children as Node[],
            node.parent?.id as number,
        );
        // no parent node, so it would be a root level node, already in the selected list, or already in the children of selected list
        if (parentNode === undefined || nodesIds.value.includes(node.id) || childNodesIds.value.includes(node.id)) {
            return;
        }
        if (nodesIds.value.includes(parentNode.id)) {
            node.selectedParentId = parentNode.id; // add the base level selectedParentId
            childNodes.value = [...childNodes.value, node]; // and then add the node
        } else if (childNodesIds.value.includes(parentNode.id)) {
            // if the parent is in the children of selected list, this node can also go in the list
            const selectedParentNode: Node = childNodes.value.find(
                (childNode) => childNode.id === parentNode.id,
            ) as Node; // find the selected parent in the list
            node.selectedParentId = selectedParentNode.selectedParentId; // add the base level selectedParentId
            childNodes.value = [...childNodes.value, node]; // and then add the node
        }
    }

    /**
     * to switch a nodes selection status
     * @param node - the node to add or remove
     * @param isChecked - to determine whether to add or remove
     */
    function switchNode(node: Node, isChecked: boolean): void {
        if (isChecked) {
            add(node);
        } else {
            remove(node);
        }
    }

    /**
     * clear all the currently selected nodes
     */
    function empty(): void {
        nodes.value.forEach((node) => remove(node));
    }

    /**
     * set the active node, which would prompt the selected-node-modal to open
     * @param node - the active Node
     */
    function setActiveNode(node: Node): void {
        activeNode.value = node;
    }

    /**
     * Loop through recursively and update all
     * children to the parents updated enabled state
     * @param node - the Node to enable/disable all children of
     * @param enabled - whether to enable or disable
     */
    function enableAllChildren(node: Node, enabled: boolean): void {
        node.enabled = enabled;
        node.children?.forEach((innerNode) => {
            enableAllChildren(innerNode, enabled);
        });
    }

    /**
     * Update the .enabled status on each node and all its children
     */
    function enableNodes(): void {
        const { enabled } = { enabled: useEnableNodesStore().enabled };
        nodes.value.forEach((node) => enableAllChildren(node, enabled)); // set the enabled on each node
        empty();
    }

    /**
     * Delete all the seleced nodes from the tree structure
     */
    function deleteNodes(): void {
        const nodesStore = useNodesStore();
        nodes.value.forEach((node) => nodesStore.deleteNode(node)); // delete each node from the tree
        empty();
    }

    return {
        nodes,
        childNodes,
        activeNode,
        hasDisabledChildNodes,
        hasNoNodes,
        hasNodes,
        count,
        singleOrPlural,
        nodesIds,
        childNodesIds,
        allNodesEnabled,
        allNodesDisabled,
        remove,
        hasActiveNode,
        checkIfSelected,
        switchNode,
        setActiveNode,
        enableNodes,
        deleteNodes,
    };
});
