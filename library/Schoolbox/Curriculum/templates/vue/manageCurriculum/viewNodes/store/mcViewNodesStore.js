/**
 * Manages the currently visible nodes in mc-view-nodes
 * those filtered by both <select> and also the search <input>
 */

import { ref, computed } from 'vue';
import { defineStore } from 'pinia';
import { useNodesStore } from '../../store/mcNodesStore';
import { useSearchStore } from '../../store/mcSearchStore';
import { UNSET_ID, findById } from '@schoolboxCurriculum/js/nodeUtils';

export const DIRECTION = {
    FORWARDS: 'forwards',
    BACKWARDS: 'backwards',
}

export const useViewNodesStore = defineStore('mcViewNodes', () => {

    const idList = ref([]); // ids of the selected nodes
    const currentDepth = ref(0); // track how far down the tree we are
    const currentDirection = ref(DIRECTION.FORWARDS); // which direction we are currently travelling in the tree


    /**
     * Can now use the search input when we have selected at least one node
     *
     * @return { Boolean }
     */
    const isActive = computed(() => {
        return idList.value.length > 0;
    });

    /**
     * to traverse back through the nodes via breadcrumbs
     *
     * @return { Array }
     */
    const breadcrumbs = computed(() => {
        const breadcrumbs = [];
        const nodesStore = useNodesStore();
        if (!nodesStore.hasSingleCurriculum) { //we only need this breadcrumb if there's more than one Curriculum, otherwise we jump straight to the single node with no option of returning back
            breadcrumbs.push( { id: nodesStore.nodes.id, name: nodesStore.nodes.name, depth: 0 } );
        }
        idList.value.forEach((id, index) => { //add in a breadcrumb for each selected node
            const nodeAtDepth = findById(nodesStore.nodes.children, id);
            breadcrumbs.push( { id: id, name: nodeAtDepth.name, depth: index+1 } );
        });
        //we return an empty array if there's no selections yet, otherwise we pull out the latest as it represents the node we are currently on (not one we need to move back to)
        return breadcrumbs.length === 0 ? [] : breadcrumbs.slice(0, -1);
    });

    /**
     * the current node selection
     *
     * @returns { Object }
     */
    const current = computed(() => {
        const nodesStore = useNodesStore();
        if (idList.value.length > 0) { //grab the current latest selection
            return findById(nodesStore.nodes.children, idList.value[currentDepth.value-1]);
        } else { //otherwise return the root level structure when there's more than one root level node
            return nodesStore.nodes;
        }
    });

    /**
     * the current nodes to display, which are either just the current selection
     * or when the search is active the results found within the current selection
     *
     * @returns { Array }
     */
    const nodes = computed(() => {
        const searchStore = useSearchStore();
        if (searchStore.isActive) {
            return searchStore.results;
        } else {
            return [current.value];
        }
    });

    /**
     * There's been a change in node selection,
     * so update based on the change in depth
     *
     * @param { number }
     * @param { number }
     */
    function setSelected (depth, id=UNSET_ID) {
        useSearchStore().clear(); //clear the search results when traversing
        if (depth === 0) { //pulling back to no node selection at all, the root level
            reset();
        } else if (depth > currentDepth.value) { //progressing to the next depth
            toNext(id);
        } else if (depth < currentDepth.value) { //or going back to a previous depth
            toPrevious(depth)
        }
        currentDepth.value = depth;
        updateHash();
    }

    /**
     * Reset to the original state, with no active selection at all
     */
    function reset () {
        currentDirection.value = DIRECTION.BACKWARDS;
        idList.value = [];
    }

    /**
     * Add one more selection
     *
     * @param { number }
     */
    function toNext (id) {
        currentDirection.value = DIRECTION.FORWARDS;
        idList.value = [...idList.value, id]; //add the new id in
    }

    /**
     * Move up to a previous node
     *
     * @param { number }
     */
    function toPrevious (targetDepth) {
        currentDirection.value = DIRECTION.BACKWARDS;
        idList.value = idList.value.filter((id, index) => index < targetDepth); //shorten the list back to the new reduced depth
    }

    /**
     * If the deleted node is currently the node visible, we need to move up one depth on the tree
     *
     * @param { object }
     */
    function checkDeletedNode (node) {
        if (node.id === current.value.id) {
            setSelected(currentDepth.value-1, idList.value[currentDepth.value-2]);
        }
    }

    /**
     * Move one depth down the tree
     *
     * @param { number }
     */
    async function setNextSelected (id) {
        const nodesStore = useNodesStore();
        await nodesStore.loadNextChildren(id);
        setSelected(currentDepth.value+1, id);
    }

    /**
     * To traverse to a specified id, regardless of where it is
     * in the current tree structure
     *
     * @param { number }
     */
    async function toId (id) {
        await useNodesStore().loadId(id);
    }

    /**
     * So if the page is refreshed the current node view is maintained
     */
    function updateHash () {
        if (current.value.id === UNSET_ID) {
            history.replaceState(undefined, undefined, '#');
        } else {
            history.replaceState(undefined, undefined, `#${current.value.id}`);
        }
    }

    return {
        isActive,
        breadcrumbs,
        current,
        nodes,
        setSelected,
        checkDeletedNode,
        setNextSelected,
        toId
    }

});