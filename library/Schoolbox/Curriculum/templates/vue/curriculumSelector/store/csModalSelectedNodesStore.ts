/**
 * Manages the currently selected nodes relevant to the current modal session
 */

/* eslint-disable import/no-cycle -- Disable dependency cycle warning temporarily [SA-18397] */

import { defineStore } from "pinia";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { findById, includeChildren, noChildrenOfNode } from "@schoolboxCurriculum/js/nodeUtils";
import { useStore } from "./curriculumSelectorStore";
import { useNodesStore } from "./csNodesStore";
import { useSelectedNodesStore } from "./csSelectedNodesStore";

export const useModalSelectedNodesStore = defineStore(`csModalSelectedNodes`, () => {
    const nodes = window.Vue.ref<Node[]>([]); // the currently selected nodes in the current modal session, with any previously selected also considered
    const childNodes = window.Vue.ref<Node[]>([]); // any children of currently selected nodes

    /**
     * Do we have any selected nodes?
     * @returns { boolean }
     */
    const hasNoNodes = window.Vue.computed(() => nodes.value.length === 0);

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
     * @param $isSelected - whether the Node is selected or not
     * @param selectedParentId - the id of the parent Node
     */
    function updateChildNodes(node: Node, $isSelected: boolean, selectedParentId: number): void {
        if ($isSelected) {
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
            updateChildNodes(innerNode, $isSelected, selectedParentId);
        });
    }

    /**
     * add a node
     * @param nodeToAdd - the node to add
     */
    function add(nodeToAdd: Node): void {
        nodes.value = noChildrenOfNode(nodes, nodeToAdd); // strip out any children of the new node, as the parent now takes precedence
        if (useStore().includeChildren) {
            nodes.value = [...nodes.value, nodeToAdd, ...includeChildren(nodeToAdd.children)]; // add the new node and all its children
        } else {
            nodes.value = [...nodes.value, nodeToAdd]; // or just add the new node
        }
        childNodes.value = removeChildNodes();
        updateChildNodes(nodeToAdd, true, nodeToAdd.id);
    }

    /**
     * to only add a single node, removing any previous selections
     * @param nodeToAdd - the node to add
     */
    function addSingle(nodeToAdd: Node): void {
        if (useStore().includeChildren) {
            nodes.value = [nodeToAdd, ...includeChildren(nodeToAdd.children)]; // add the new node and all its children
        } else {
            nodes.value = [nodeToAdd]; // or just add the new node
        }
        childNodes.value = removeChildNodes();
        updateChildNodes(nodeToAdd, true, nodeToAdd.id);
    }

    /**
     * remove a node
     * @param nodeToRemove - the node to remove
     */
    function remove(nodeToRemove: Node): void {
        updateChildNodes(nodeToRemove, false, nodeToRemove.id);
        if (useStore().includeChildren) {
            const idsToRemove = [nodeToRemove, ...includeChildren(nodeToRemove.children)].map((node) => node.id); // get the id of the node to remove and all its children
            nodes.value = nodes.value.filter((node) => !idsToRemove.includes(node.id)); // remove the node and all its children
        } else {
            nodes.value = nodes.value.filter((node) => node.id !== nodeToRemove.id); // or just remove the node
        }
    }

    /**
     * populate with the currently selected nodes
     */
    function refresh(): void {
        nodes.value = useSelectedNodesStore().nodes; // ensure current selections are reflected in the new modal session
        childNodes.value = removeChildNodes();
        nodes.value.forEach((node) => {
            updateChildNodes(node, true, node.id);
        });
    }

    /**
     * to track whether a node is selected (with cs-modal-node-checkbox)
     * for each newly loaded node, we need to check whether its parent has been selected
     * to do this we check if the parent of the loaded node is already in the child nodes list
     * if it is we also need to add in this node
     * @param node - to determine whether the Node is already selected
     */
    function checkIfSelected(node: Node): void {
        const parentNode: Node | undefined = findById(
            (useNodesStore().nodes as Node).children as Node[],
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
     * @param node - the node to switch
     * @param isChecked - whether the node is checked or not
     */
    function switchNode(node: Node, isChecked: boolean): void {
        if (isChecked) {
            add(node);
        } else {
            remove(node);
        }
    }

    /**
     * to check if a node is selected
     * @param node - the node to check
     * @returns - whether the node is selected or not
     */
    function isSelected(node: Node): boolean {
        return nodesIds.value.includes(node.id);
    }

    /**
     * clear all the currently selected nodes
     */
    function empty(): void {
        nodes.value.forEach((node) => remove(node));
    }

    /**
     * update the currently selected with the latest modal session
     */
    function update(): void {
        useSelectedNodesStore().update(nodes.value);
    }

    return {
        nodes,
        hasNoNodes,
        nodesIds,
        childNodesIds,
        refresh,
        add,
        addSingle,
        remove,
        checkIfSelected,
        isSelected,
        switchNode,
        empty,
        update,
    };
});
