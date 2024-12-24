/**
 * The store to manage the nodes linked to a folder.
 * As several of these stores could be created in a single Vue App (for example in the Unit Planner),
 * we need to be able to create a store for each folder, and manage the nodes for that folder.
 * So we pass the folderId and componentInstanceId to the useStore function to create a unique store for each folder.
 */

import { defineStore } from "pinia";
import { activate, timeout } from "@schoolboxVue/components/toast/sbxToastEvent";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { UNSET_ID, findById, includeChildren } from "@schoolboxCurriculum/js/nodeUtils";

const FOLDER_NODES_UPDATED = "folderNodesStore__UPDATED"; // the event to trigger when the nodes have been updated

/**
 * The details passed with an UPDATE event
 */
type UpdateEventDetail = {
    folderId: number;
    componentInstanceId: number;
    updatedNodes: Node[];
    updatedSelectedNodes: Node[];
};

// eslint-disable-next-line @typescript-eslint/explicit-function-return-type -- we want the inferred type from defineStore
export const useStore = (folderId: number, componentInstanceId: number) =>
    defineStore(`folderNodes/${folderId}-${componentInstanceId}`, () => {
        const nodes = window.Vue.ref<Node[]>([]); // the current nodes to display the entire tree of
        const selectedNodes = window.Vue.ref<Node[]>([]); // the specific nodes with codes that have been selected
        const filter = window.Vue.ref<Node | null>({} as Node | null); // to only display nodes that are children of this node
        const isReadOnly = window.Vue.ref<boolean>(true); // whether the user can edit nodes or not
        const selectedNodeIds = window.Vue.computed(() => selectedNodes.value.map((node) => node.id));
        const selectedDetailsNodeId = window.Vue.ref<number>(UNSET_ID); // when changed will trigger the curriculum-details-modal to open
        const isProcessing = window.Vue.ref<boolean>(false); // to disable further backend actions while we're already processing
        const nodeToRemove = window.Vue.ref<Node | null>(null); // the node to remove
        const hasLoaded = window.Vue.ref<boolean>(false); // whether the store has been initialised, so nodes aren't displayed until the filter has been considered

        /**
         * To notify the backend of a change in the selected nodes
         */
        async function pushChanges(): Promise<void> {
            try {
                activate("Updating...");
                isProcessing.value = true;
                const response = await fetch(`/api/curriculum/usage/folder/${folderId}?returnFormat=tree&descendants=0`, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify([...selectedNodes.value]),
                });
                if (response.ok) {
                    const newNodes = (await response.json()) as Node[];
                    nodes.value = newNodes;
                    timeout({ text: "Curriculum updated successfully" });
                    document.dispatchEvent(
                        new CustomEvent(FOLDER_NODES_UPDATED, {
                            detail: {
                                folderId,
                                componentInstanceId,
                                updatedNodes: newNodes,
                                updatedSelectedNodes: selectedNodes.value,
                            } as UpdateEventDetail,
                        }),
                    );
                } else {
                    const responseText = (await response.json()) as { title: string; type: string; status: number };
                    timeout({ text: responseText.title, isSuccess: false });
                }
            } catch (error) {
                timeout({ text: "There was an unexpected error", isSuccess: false });
            } finally {
                isProcessing.value = false;
            }
        }

        /**
         * Multiple instances of the curriculum overview component could exist on the page,
         * even in entirely different Vue apps.
         * As such we need to ensure that when a change is made in one instance,
         * the other instances are updated to reflect the latest changes
         */
        function initEventListener(): void {
            document.addEventListener(FOLDER_NODES_UPDATED, (event) => {
                const eventDetail = (event as CustomEvent).detail as UpdateEventDetail;
                if (eventDetail.folderId === folderId && eventDetail.componentInstanceId !== componentInstanceId) {
                    nodes.value = eventDetail.updatedNodes;
                    selectedNodes.value = eventDetail.updatedSelectedNodes;
                }
            });
        }

        /**
         * Store a reference to the nodes to display
         * @param $selectedNodes - the nodes with codes that have been selected
         * @param $nodes - the node trees to display
         * @param $filter - to only display nodes that are children of this node
         * @param $isReadOnly - whether the user can edit nodes or not
         */
        function init($selectedNodes: Node[], $nodes: Node[], $filter: Node | null, $isReadOnly: boolean): void {
            selectedNodes.value = $selectedNodes;
            nodes.value = $nodes;
            filter.value = $filter;
            isReadOnly.value = $isReadOnly;
            hasLoaded.value = true;
            initEventListener();
        }

        /**
         * We need to filter the nodes to only display the children of the filter node,
         * first checking if the node is a child of the existing node trees
         * and if so only returning the children of the filter node
         * @returns {Node[]} - the nodes that are children of the filter node
         */
        const filteredNodes = window.Vue.computed(() => {
            // Nothing selected? Just show all nodes
            if (filter.value === null) {
                return nodes.value;
            }
            const filterNode = findById(nodes.value, filter.value.id);
            if (filterNode === undefined) {
                // if the filter node isn't within the list of nodes, we'll just return an empty node, highlighting that the filter node doesn't currently contain any selected nodes
                return [];
            }
            if (filterNode.children === null) {
                // if the filter node has no children, we'll just return the filter node, as it's the only node to display
                return [filterNode];
            }
            // when the filter is set and has children, it's assumed the component heading will be the name of the filter node, so there's no need to display the filter node itself, as we'd just be doubling up on the name
            return [...filterNode.children];
        });

        /**
         * To check if there are any nodes to display
         * @returns {boolean} - whether there are any nodes to display
         */
        const hasNodes = window.Vue.computed(() => nodes.value.length > 0);

        /**
         * To check if there are any filtered nodes to display
         * @returns {boolean} - whether there are any filtered nodes to display
         */
        const hasFilteredNodes = window.Vue.computed(() => filteredNodes.value.length > 0);

        /**
         * To check if there are any nodes to display after filtering, as we may have nodes linked to the folder,
         * but the current filter node selection does not contain any of these selected nodes
         * @returns {boolean} - whether there are any nodes to display after filtering
         */
        const hasHiddenNodes = window.Vue.computed(() => hasNodes.value && filteredNodes.value.length === 0);

        /**
         * To remove a node from the selectedNodes, after which we'll notify the backend of the change
         * @param node - the node to remove
         */
        function removeNode(node: Node): void {
            nodeToRemove.value = node;
        }

        /**
         * Confirm the removal of the node
         */
        async function confirmRemoveNode(): Promise<void> {
            if (nodeToRemove.value === null) {
                return;
            }
            // get the id of the node to remove and all its children
            const idsToRemove = [nodeToRemove.value, ...includeChildren(nodeToRemove.value.children)].map(
                (node) => node.id,
            );
            selectedNodes.value = selectedNodes.value.filter((node) => !idsToRemove.includes(node.id)); // remove the node and all its children
            await pushChanges();
            nodeToRemove.value = null;
        }

        /**
         * Cancel the removal of the node
         */
        function cancelRemoveNode(): void {
            nodeToRemove.value = null;
        }

        /**
         * To update the selectedNodes with new nodes, after which we'll notify the backend of the change
         * @param $nodes - the new nodes to update the selectedNodes with
         */
        async function updateNodes($nodes: Node[]): Promise<void> {
            selectedNodes.value = $nodes;
            await pushChanges();
        }

        return {
            nodes,
            selectedNodes,
            filter,
            filteredNodes,
            isReadOnly,
            selectedNodeIds,
            hasNodes,
            hasFilteredNodes,
            hasHiddenNodes,
            selectedDetailsNodeId,
            isProcessing,
            nodeToRemove,
            hasLoaded,
            init,
            removeNode,
            confirmRemoveNode,
            cancelRemoveNode,
            updateNodes,
        };
    });
