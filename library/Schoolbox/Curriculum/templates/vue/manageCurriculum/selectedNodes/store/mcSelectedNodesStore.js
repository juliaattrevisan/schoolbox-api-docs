/**
 * Manage the currently selected curriculum nodes.
 * A selected node can be bulk enabled or deleted.
 *
 * Note the distinciton with an 'active' node, handled in mcActiveNodeStore
 * which is used to update or add a subsection to a SINGLE node.
 */

import { ref, computed } from 'vue';
import { defineStore } from 'pinia';
import { useNodesStore } from '../../store/mcNodesStore';
import { UNSET_ID, findById, getEmptyNode } from '@schoolboxCurriculum/js/nodeUtils';
import { useEnableNodesStore } from './mcEnableNodesStore';


export const useSelectedNodesStore = defineStore('mcSelectedNodes', () => {

    const nodes = ref([]); // the currently selected nodes
    const childNodes = ref([]); // any children of currently selected nodes
    const activeNode = ref(getEmptyNode()); // to show extra info on a selected code in a modal
    const hasDisabledChildNodes = ref(false);

    /**
     * Do we have any selected nodes?
     *
     * @return { Boolean }
     */
    const hasNoNodes = computed(() => {
        return nodes.value.length === 0;
    });

    /**
     * Do we have any selected nodes?
     *
     * @return { Boolean }
     */
    const hasNodes = computed(() => {
        return nodes.value.length > 0;
    });

    /**
     * Number of nodes
     *
     * @return { number }
     */
    const count = computed(() => {
        return nodes.value.length;
    });

    /**
     * Return the single or plural depending on node selection
     *
     * @return { string }
     */
    const singleOrPlural = computed(() => {
        return count.value > 1 ? 'nodes' : 'node';
    });

    /**
     * just return an array of ids, used to compare if a node is selected or not
     *
     * @return { array }
     */
    const nodesIds = computed(() => {
        return nodes.value.map((node) => node.id);
    });

    /**
     * just return an array of ids, used to compare if a node is selected or not
     *
     * @return { array }
     */
    const childNodesIds = computed(() => {
        return childNodes.value.map((node) => node.id);
    });

    /**
     * check if all nodes are enabled so we can just show the disable button
     *
     * @return { boolean }
     */
    const allNodesEnabled = computed(() => {
        if (hasNodes.value) {
            const enabledNodes = nodes.value.filter((node) => node.enabled);
            return enabledNodes.length === nodes.value.length;
        } else {
            return false;
        }
    });

    /**
     * check if all nodes are disabled so we can just show the enable button
     * disabled child nodes cant be enabled - only the disabled parent can be enabled
     *
     * @return { boolean }
     */
    const allNodesDisabled = computed(() => {
        if (hasNodes.value) {
            const enabledNodes = nodes.value.filter((node) => !node.enabled);
            const nodesStore = useNodesStore();
            const noChildEnabledNodes = enabledNodes.filter((node) => { //prevent enabling child nodes of disabled parents
                if (node.parent) { //could be the root level node, so won't have a parent
                    const parentNode = findById(nodesStore.nodes.children, node.parent.id);
                    return parentNode.enabled ? true : false;
                } else {
                    return node.enabled ? false : true;
                }
            });
            hasDisabledChildNodes.value = noChildEnabledNodes.length !== enabledNodes.length ? true : false; //show a note in the the UI that enabling child nodes of disabled parents is not possible
            return noChildEnabledNodes.length === nodes.value.length;
        } else {
            return false;
        }
    });

    /**
     * To show further info on a selected node
     *
     * @return { Boolean }
     */
    const hasActiveNode = computed(() => {
        return activeNode.value.id !== UNSET_ID ? true : false;
    });

    /**
     * add a node
     *
     * @param { Object  }
     */
    function add (nodeToAdd) {
        nodes.value = noChildrenOfNode(nodeToAdd);
        nodes.value = [...nodes.value, nodeToAdd];
        childNodes.value = removeChildNodes();
        updateChildNodes(nodeToAdd, true, nodeToAdd.id);
    }

    /**
     * we need to check if a new parent has been selected of any existing children,
     * if it has the children should be removed as the parent now takes precedence
     *
     * @param { Object }
     *
     * @returns { array }
     */
    function noChildrenOfNode (nodeToAdd) {
        const noChildrenOfNode = nodes.value.filter((selectedNode) => {
            if (nodeToAdd.children) { //only if the children have loaded
                const node = findById(nodeToAdd.children, selectedNode.id);
                return node !== undefined ? false : true; //only keep those nodes that aren't a child of the new node
            } else { //otherwise no existing nodes will be children, as they haven't been loaded and so couldn't have been selected
                return true;
            }
        });
        return noChildrenOfNode;
    }

    /**
     * remove any existing child nodes that don't have a .selectedParentId that is an id contained in nodes
     *
     * @returns { array }
     */
    function removeChildNodes () {
        return childNodes.value.filter((selectedChildNode) => {
            return nodesIds.value.includes(selectedChildNode.selectedParentId);
        });
    }

    /**
     * remove a node
     *
     * @param { Object }
     */
    function remove (nodeToRemove) {
        updateChildNodes(nodeToRemove, false, nodeToRemove.id);
        nodes.value = nodes.value.filter((node) => node.id !== nodeToRemove.id);
    }

    /**
     * to track whether a node is selected (with mc-view-node-checkbox)
     * for each newly loaded node, we need to check whether its parent has been selected
     * to do this we check if the parent of the loaded node is already in the child nodes list
     * if it is we also need to add in this node
     *
     * @param { object }
     */
    function checkIfSelected (node) {
        const parentNode = findById(useNodesStore().nodes.children, node.parent?.id);
        //no parent node, so it would be a root level node, already in the selected list, or already in the children of selected list
        if (parentNode === undefined || nodesIds.value.includes(node.id) || childNodesIds.value.includes(node.id)) {
            return;
        } else if (nodesIds.value.includes(parentNode.id)) {
            node.selectedParentId = parentNode.id; //add the base level selectedParentId
            childNodes.value  = [...childNodes.value, node]; //and then add the node
        } else if (childNodesIds.value.includes(parentNode.id)) { //if the parent is in the children of selected list, this node can also go in the list
            const selectedParentNode = childNodes.value.find((node) => node.id === parentNode.id); //find the selected parent in the list
            node.selectedParentId = selectedParentNode.selectedParentId; //add the base level selectedParentId
            childNodes.value  = [...childNodes.value, node]; //and then add the node
        }
    }

    /**
     * recursively loop through and update the childNodes array
     * adding any loaded children when the parent node is selected
     * or removing any loaded children from the node just removed
     *
     * @param { object }
     * @param { boolean }
     * @param { number }
     */
    function updateChildNodes (node, isSelected, selectedParentId) {
        if (isSelected) {
            if (!nodesIds.value.includes(node.id) && !childNodesIds.value.includes(node.id)) { //don't include the parent node and nodes already in the child list
                node.selectedParentId = selectedParentId;
                childNodes .value= [...childNodes.value, node];
            }
        } else { //use the .selectedParentId property to determine which child nodes to remove from the list
            childNodes.value = childNodes.value.filter((selectedCode) => selectedCode.selectedParentId !== selectedParentId);
        }
        node.children?.forEach((innerNode) => {
            updateChildNodes(innerNode, isSelected, selectedParentId);
        });
    }

    /**
     * to switch a nodes selection status
     *
     * @param { object }
     * @param { boolean }
     */
    function switchNode (node, isChecked) {
        isChecked ? add(node) : remove(node);
    }

    /**
     * clear all the currently selected nodes
     */
    function empty () {
        nodes.value.forEach((node) => remove(node));
    }

    /**
     * set the active node, which would prompt the selected-node-modal to open
     *
     * @param { object }
     */
    function setActiveNode (node) {
        activeNode.value = node;
    }

    /**
     * Update the .enabled status on each node and all its children
     *
     * @param { object }
     */
    function enableNodes () {
        const enabled = useEnableNodesStore().enabled;
        nodes.value.forEach((node) => enableAllChildren(node, enabled)); //set the enabled on each node
        empty();
    }

    /**
     * Loop through recursively and update all
     * children to the parents updated enabled state
     *
     * @param { array }
     * @param { boolean }
     */
    function enableAllChildren (node, enabled) {
        node.enabled = enabled;
        node.children?.forEach((innerNode) => {
            enableAllChildren(innerNode, enabled);
        });
    }

    /**
     * Delete all the seleced nodes from the tree structure
     */
    function deleteNodes () {
        const nodesStore = useNodesStore();
        nodes.value.forEach((node) => nodesStore.deleteNode(node)); //delete each node from the tree
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
        deleteNodes
    }

})

