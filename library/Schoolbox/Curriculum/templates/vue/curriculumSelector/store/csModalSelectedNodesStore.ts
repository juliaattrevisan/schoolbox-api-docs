/**
 * Manages the currently selected nodes relevant to the current modal session
 */

import { defineStore } from "pinia";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { useSelectedNodesStore } from "./csSelectedNodesStore";

export const useModalSelectedNodesStore = defineStore(`csModalSelectedNodes`, () => {
    const nodes = window.Vue.ref<Node[]>([]); // the currently selected nodes in the current modal session, with any previously selected also considered

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
     * populate with the currently selected nodes
     */
    function refresh(): void {
        nodes.value = useSelectedNodesStore().nodes; // ensure current selections are reflected in the new modal session
    }

    /**
     * add a node
     * @param nodeToAdd - the node to add
     */
    function add(nodeToAdd: Node): void {
        nodes.value = [...nodes.value, nodeToAdd];
    }

    /**
     * to only add a single node, removing any previous selections
     * @param nodeToAdd - the node to add
     */
    function addSingle(nodeToAdd: Node): void {
        nodes.value = [nodeToAdd];
    }

    /**
     * remove a node
     * @param nodeToRemove - the node to remove
     */
    function remove(nodeToRemove: Node): void {
        nodes.value = nodes.value.filter((node) => node.id !== nodeToRemove.id);
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
        refresh,
        add,
        addSingle,
        remove,
        isSelected,
        switchNode,
        empty,
        update,
    };
});
