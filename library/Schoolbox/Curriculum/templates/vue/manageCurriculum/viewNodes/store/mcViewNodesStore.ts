/**
 * Manages the currently visible nodes in mc-view-nodes
 * those filtered by both <select> and also the search <input>
 */

/* eslint-disable import/no-cycle -- Disable dependency cycle warning temporarily [SA-18428] */

import { defineStore } from "pinia";
import { useNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/store/mcNodesStore";
import { useSearchStore } from "@schoolboxCurriculum/vue/manageCurriculum/store/mcSearchStore";
import { UNSET_ID, findById } from "@schoolboxCurriculum/js/nodeUtils";
import type { Node } from "@schoolboxCurriculum/types/Node";
import type { Breadcrumb } from "@schoolboxCurriculum/types/Breadcrumb";

export const DIRECTION = {
    FORWARDS: "forwards",
    BACKWARDS: "backwards",
};

/**
 * To allow for browser back and forward buttons,
 * we need to pass around the node id to know where to move to
 */
type PopState = {
    nodeId: number;
};

export const useViewNodesStore = defineStore("mcViewNodes", () => {
    const idList = window.Vue.ref<number[]>([]); // ids of the selected nodes
    const currentDepth = window.Vue.ref(0); // track how far down the tree we are
    const currentDirection = window.Vue.ref(DIRECTION.FORWARDS); // which direction we are currently travelling in the tree
    const showDisabled = window.Vue.ref(true); // whether only enabled nodes should appear
    const isBrowserAction = window.Vue.ref(false); // whether the current action is a browser action (backward or forward press)

    /**
     * Can now use the search input when we have selected at least one node
     * @returns { boolean }
     */
    const isActive = window.Vue.computed(() => idList.value.length > 0);

    /**
     * to traverse back through the nodes via breadcrumbs
     * @returns { Array }
     */
    const breadcrumbs = window.Vue.computed(() => {
        const currentBreadcrumbs: Breadcrumb[] = [];
        const nodesStore = useNodesStore();
        if (!nodesStore.hasSingleCurriculum) {
            // we only need this breadcrumb if there's more than one Curriculum, otherwise we jump straight to the single node with no option of returning back
            currentBreadcrumbs.push({ id: nodesStore.nodes.id, name: nodesStore.nodes.name, depth: 0 });
        }
        idList.value.forEach((id, index) => {
            // add in a breadcrumb for each selected node
            const nodeAtDepth: Node = findById(nodesStore.nodes.children as Node[], id) as Node;
            currentBreadcrumbs.push({ id, name: nodeAtDepth.name, depth: index + 1 });
        });
        // we return an empty array if there's no selections yet, otherwise return the breadcrumbs
        return currentBreadcrumbs.length === 0 ? [] : currentBreadcrumbs;
    });

    /**
     * the current node selection
     * @returns { Node }
     */
    const current = window.Vue.computed(() => {
        const nodesStore = useNodesStore();
        if (idList.value.length > 0) {
            // grab the current latest selection
            return findById(nodesStore.nodes.children as Node[], idList.value[currentDepth.value - 1]) as Node;
        }
        // otherwise return the root level structure when there's more than one root level node
        return nodesStore.nodes as Node;
    });

    /**
     * the current nodes to display, which are either just the current selection
     * or when the search is active the results found within the current selection
     * @returns { Node[] }
     */
    const nodes = window.Vue.computed<Node[]>(() => {
        const searchStore = useSearchStore();
        if (searchStore.isActive) {
            return searchStore.results;
        }
        return [current.value] as Node[];
    });

    /**
     * Reset to the original state, with no active selection at all
     */
    function reset() {
        currentDirection.value = DIRECTION.BACKWARDS;
        idList.value = [];
    }

    /**
     * Add one more selection
     * @param { number } id - id of the Node to move to
     */
    function toNext(id: number) {
        currentDirection.value = DIRECTION.FORWARDS;
        idList.value = [...idList.value, id]; // add the new id in
    }

    /**
     * Move up to a previous node
     * @param { number } targetDepth - the depth to move back to
     */
    function toPrevious(targetDepth: number) {
        currentDirection.value = DIRECTION.BACKWARDS;
        idList.value = idList.value.filter((id, index) => index < targetDepth); // shorten the list back to the new reduced depth
    }

    /**
     * So if the page is refreshed the current node view is maintained
     * and to allow for browser back and forward buttons
     */
    function updateHash() {
        // we dont need to update the browser state if this is already a browser action
        if (isBrowserAction.value) {
            return;
        }
        /**
         * don't update the history state while we are traversing the tree,
         * this occurs while moving to a selected searched node or moving to the node id via the initial page hash,
         * otherwise each single node traversal will be added to the history
         */
        if (useNodesStore().isLoadingAllChildren) {
            return;
        }
        if (current.value.id === UNSET_ID) {
            window.history.pushState({ nodeId: UNSET_ID } as PopState, "", window.location.pathname);
        } else {
            window.history.pushState({ nodeId: current.value.id } as PopState, "", `#${current.value.id}`);
        }
    }

    /**
     * There's been a change in node selection,
     * so update based on the change in depth
     * @param { number } depth - the depth to move to
     * @param { number } id - the id of the selected Node
     */
    function setSelected(depth: number, id: number = UNSET_ID) {
        useSearchStore().clear(); // clear the search results when traversing
        if (depth === 0) {
            // pulling back to no node selection at all, the root level
            reset();
        } else if (depth > currentDepth.value) {
            // progressing to the next depth
            toNext(id);
        } else if (depth < currentDepth.value) {
            // or going back to a previous depth
            toPrevious(depth);
        }
        currentDepth.value = depth;
        updateHash();
    }

    /**
     * If the deleted node is currently the node visible, we need to move up one depth on the tree
     * @param { Node } node - node to check
     */
    function checkDeletedNode(node: Node) {
        if (node.id === current.value.id) {
            setSelected(currentDepth.value - 1, idList.value[currentDepth.value - 2]);
        }
    }

    /**
     * Move one depth down the tree
     * @param { number } id - id of the Node to select
     */
    async function setNextSelected(id: number) {
        const nodesStore = useNodesStore();
        await nodesStore.loadNextChildren(id);
        setSelected(currentDepth.value + 1, id);
    }

    /**
     * To traverse to a specified id, regardless of where it is
     * in the current tree structure
     * @param { number } id - id of the Node to move to
     */
    async function toId(id: number) {
        await useNodesStore().loadId(id);
    }

    /**
     * To responsd to browser backward and forward button actions
     * @param { object } state -
     */
    async function updateOnPopState(state: PopState) {
        // if the state contains a valid node id then move to that node id
        if (state && state.nodeId && Number.isInteger(state.nodeId) && state.nodeId !== UNSET_ID) {
            // check if the nodeId is currently in the idList, if so move backward to that index
            isBrowserAction.value = true;
            const index = idList.value.indexOf(state.nodeId);
            if (index > -1) {
                setSelected(index + 1, state.nodeId);
            } else {
                // otherwise move forward to the node id
                await toId(state.nodeId);
            }
            isBrowserAction.value = false;
        } else {
            // otherwise just reset to the base level
            isBrowserAction.value = true;
            setSelected(0, UNSET_ID);
            isBrowserAction.value = false;
        }
    }

    return {
        showDisabled,
        currentDirection,
        isActive,
        breadcrumbs,
        current,
        nodes,
        setSelected,
        checkDeletedNode,
        setNextSelected,
        toId,
        updateOnPopState,
    };
});
