/**
 * The store to manage the nodes linked to a folder.
 * As several of these stores could be created in a single Vue App (for example in the Unit Planner),
 * we need to be able to create a store for each folder, and manage the nodes for that folder.
 */

import { defineStore } from "pinia";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";

export const useStore = (folderId: number) =>
    defineStore(`folderNodes/${folderId}`, () => {
        const nodes = window.Vue.ref<Node[]>([]); // the current nodes to display the entire tree of
        const selectedNodes = window.Vue.ref<Node[]>([]); // the specific nodes with codes that have been selected
        const selectedNodeIds = window.Vue.computed(() => selectedNodes.value.map((node) => node.id));
        const hasNoNodes = window.Vue.computed(() => nodes.value.length === 0);
        const selectedDetailsNodeId = window.Vue.ref<number>(UNSET_ID); // when changed will trigger the curriculum-details-modal to open
        const isProcessing = window.Vue.ref<boolean>(false); // to disable further backend actions while we're already processing

        /**
         * Store a reference to the nodes to display
         * @param {Node[]} $nodes - the node trees to display
         * @param {Node[]} $selectedNodes - the nodes with codes that have been selected
         */
        function init($nodes: Node[], $selectedNodes: Node[]): void {
            nodes.value = $nodes;
            selectedNodes.value = $selectedNodes;
        }

        /**
         * To notify the backend of a change in the selected nodes
         */
        async function pushChanges() {
            try {
                isProcessing.value = true;
                const response = await fetch(`/api/curriculum/usage/folder/${folderId}?returnFormat=tree`, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify([...selectedNodes.value]),
                });
                const newNodes = (await response.json()) as Node[];
                nodes.value = newNodes;
            } catch (error) {
                throw new Error();
            } finally {
                isProcessing.value = false;
            }
        }

        /**
         * To remove a node from the selectedNodes, after which we'll notify the backend of the change
         * @param {Node} node - the node to remove
         */
        async function removeNode(node: Node): Promise<void> {
            selectedNodes.value = selectedNodes.value.filter((selectedNode) => selectedNode.id !== node.id);
            await pushChanges();
        }

        /**
         * To update the selectedNodes with new nodes, after which we'll notify the backend of the change
         * @param {Node[]} $nodes - the new nodes to update the selectedNodes with
         */
        async function updateNodes($nodes: Node[]): Promise<void> {
            selectedNodes.value = $nodes;
            await pushChanges();
        }

        return {
            nodes,
            selectedNodes,
            selectedNodeIds,
            hasNoNodes,
            selectedDetailsNodeId,
            isProcessing,
            init,
            removeNode,
            updateNodes,
        };
    });
