/**
 * The store to manage the nodes linked to a folder.
 * As several of these stores could be created in a single Vue App (for example in the Unit Planner),
 * we need to be able to create a store for each folder, and manage the nodes for that folder.
 */

import { defineStore } from "pinia";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csSelectedNodesStore";

export const useStore = (folderId: number) =>
    defineStore(`folderNodes/${folderId}`, () => {
        const nodes = window.Vue.ref<Node[]>([]); // the current nodes to display the entire tree of
        const hasNoNodes = window.Vue.computed(() => nodes.value.length === 0);
        const selectedDetailsNodeId = window.Vue.ref<number>(UNSET_ID); // the currently selected node id to display the details of
        const selectedNodesStore = useSelectedNodesStore();

        /**
         * to update the backend with the latest selected nodes, so the nodeTree can be updated
         */
        window.Vue.watch(
            () => selectedNodesStore.nodes,
            () => {
                // notify the backend of the latest selected nodes, updating the nodeTree with the returned data
            },
        );

        /**
         * Store a reference to the nodes to display
         * @param {Node[]} $nodes - the node trees to display
         */
        function init($nodes: Node[]): void {
            nodes.value = $nodes;
        }

        /**
         * To remove a node from the nodeTree, pass through the the selectedNodesStore
         * which will then trigger the watch to update the nodeTree
         * @param {Node} node - the node to remove
         */
        function removeSelectedNode(node: Node): void {
            selectedNodesStore.remove(node);
        }

        return {
            nodes,
            hasNoNodes,
            selectedDetailsNodeId,
            selectedNodesStore,
            init,
            removeSelectedNode,
        };
    });
