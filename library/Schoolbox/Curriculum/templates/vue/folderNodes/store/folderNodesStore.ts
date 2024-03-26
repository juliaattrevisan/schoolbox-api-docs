/**
 * The store to manage the nodes linked to a folder.
 * As several of these stores could be created in a single Vue App (for example in the Unit Planner),
 * we need to be able to create a store for each folder, and manage the nodes for that folder.
 * So we padd the folderId to the useStore function to create a unique store for each folder.
 */

import { defineStore } from "pinia";
import { ToastEvent } from "@schoolboxVue/components/toast/sbxToastEvent";
import type { ToastActivateEventDetail, ToastTimeoutEventDetail } from "@schoolboxVue/components/toast/sbxToastEvent";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";

export const useStore = (folderId: number) =>
    defineStore(`folderNodes/${folderId}`, () => {
        const nodes = window.Vue.ref<Node[]>([]); // the current nodes to display the entire tree of
        const selectedNodes = window.Vue.ref<Node[]>([]); // the specific nodes with codes that have been selected
        const filter = window.Vue.ref<Node>({} as Node); // the node filter to apply to the nodes, only displaying nodes that are children of this node
        const isReadOnly = window.Vue.ref<boolean>(true); // whether the user can edit nodes or not
        const selectedNodeIds = window.Vue.computed(() => selectedNodes.value.map((node) => node.id));
        const hasNodes = window.Vue.computed(() => nodes.value.length > 0);
        const selectedDetailsNodeId = window.Vue.ref<number>(UNSET_ID); // when changed will trigger the curriculum-details-modal to open
        const isProcessing = window.Vue.ref<boolean>(false); // to disable further backend actions while we're already processing

        /**
         * To notify the backend of a change in the selected nodes
         */
        async function pushChanges() {
            try {
                document.dispatchEvent(
                    new CustomEvent(ToastEvent.ACTIVATE, {
                        detail: { text: "Saving..." } as ToastActivateEventDetail,
                    }),
                );
                isProcessing.value = true;
                const response = await fetch(`/api/curriculum/usage/folder/${folderId}?returnFormat=tree`, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify([...selectedNodes.value]),
                });
                if (response.ok) {
                    const newNodes = (await response.json()) as Node[];
                    nodes.value = newNodes;
                    document.dispatchEvent(
                        new CustomEvent(ToastEvent.TIMEOUT, {
                            detail: { text: "Curriculum updated" } as ToastTimeoutEventDetail,
                        }),
                    );
                } else {
                    const responseText = (await response.json()) as { title: string; type: string; status: number };
                    document.dispatchEvent(
                        new CustomEvent(ToastEvent.TIMEOUT, {
                            detail: { text: responseText.title, isSuccess: false } as ToastTimeoutEventDetail,
                        }),
                    );
                }
            } catch (error) {
                document.dispatchEvent(
                    new CustomEvent(ToastEvent.TIMEOUT, {
                        detail: { text: "There was an unexpected error", isSuccess: false } as ToastTimeoutEventDetail,
                    }),
                );
            } finally {
                isProcessing.value = false;
            }
        }

        /**
         * Store a reference to the nodes to display
         * @param {Node[]} $nodes - the node trees to display
         * @param {Node[]} $selectedNodes - the nodes with codes that have been selected
         * @param {Node} $filter - the node filter to apply to the nodes, only displaying nodes that are children of this node
         * @param {boolean} $isReadOnly - whether the user can edit nodes or not
         */
        function init($nodes: Node[], $selectedNodes: Node[], $filter: Node, $isReadOnly: boolean): void {
            nodes.value = $nodes;
            selectedNodes.value = $selectedNodes;
            filter.value = $filter;
            isReadOnly.value = $isReadOnly;
            void pushChanges();
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
            isReadOnly,
            selectedNodeIds,
            hasNodes,
            selectedDetailsNodeId,
            isProcessing,
            init,
            removeNode,
            updateNodes,
        };
    });
