/**
 * Stores all the curriculum codes and determines display of nodes
 */

import { ref, computed } from 'vue';
import { defineStore } from 'pinia';
import { UNSET_ID, findById, sortByName, removeId, getBreadcrumbs } from '@schoolboxCurriculum/js/nodeUtils';
import { useStore } from './manageCurriculumStore';
import { useViewNodesStore } from '../viewNodes/store/mcViewNodesStore';
import { useSelectedNodesStore } from '../selectedNodes/store/mcSelectedNodesStore';


export const useNodesStore = defineStore('mcNodes', () => {

    const nodes = ref({}); // all the nodes currently loaded
    const isLoading = ref(false); // to display loading note
    const hasLoadError = ref(false); // to display loading error

    /**
     * If there's only no Curriculum, display an empty state
     *
     * @return { Boolean }
     */
    const hasNoCurriculum = computed(() => {
        return nodes.value.children && nodes.value.children.length === 0 ? true : false;
    });

    /**
     * If there's only one curriculum, we can select it
     * straight away, otherwise we allow the user to make the selection
     *
     * @return { Boolean }
     */
    const hasSingleCurriculum = computed(() => {
        return nodes.value.children && nodes.value.children.length === 1 ? true : false;
    });

    /**
     * load in the top level nodes from the api
     */
    async function load () {
        const store = useStore();
        try {
            const response = await fetch(`${store.getApiEndpoint()}/?topLevelOnly=1&descendants=1&limit=50`, {
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            if (response.ok) {
                const topLevelNodes = await response.json();
                await setTopLevel(topLevelNodes);
            } else {
                store.hasLoadError = true; //show the load error display state
            }
        } catch (error) {
            console.warn(error);
        } finally {
            store.isLoading = false;
        }
    }

    /**
     * set the top level nodes, passed through from loading
     *
     * @param { object }
     */
    async function setTopLevel (nodes) {
        updateNodes(nodes);
        const store = useStore();
        if (store.hasId) {
            await loadId(store.id);
        } else if (hasSingleCurriculum.value) { //we jump straight to the only node available if there's only one
            await loadSingleCurriculum();
        }
    }

    /**
     * load the single curriculum for display straight away
     */
    async function loadSingleCurriculum () {
        const singleCurriculumId = nodes.value.children[0].id;
        await useViewNodesStore().setNextSelected(singleCurriculumId);
    }

    /**
     * load the node, retrieving all its parents so we can traverse directly to it
     *
     * @param { number }
     */
    async function loadId (id) {
        try {
            isLoading.value = true;
            const response = await fetch(`${useStore().getApiEndpoint()}/${id}?ancestors=99`); //just use 99 as hopefully there's no tree that deep...
            if (response.ok) {
                const nodeWithParents = await response.json();
                await loadAllChildren(nodeWithParents);
            } else {
                hasId.value = false; //as the id couldn't be found
                if (hasSingleCurriculum.value) { //we should still jump straight to the only node available if there's only one
                    await loadSingleCurriculum();
                }
                if (response.status === 404) { //and we assume the id can't be found, displaying an error
                    throw new Error(`No node id matching ${id} has been found`);
                }
            }
        } catch (error) {
            hasLoadError.value = true;
            throw new Error(error.message, error.options);
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * load in all the children required to reach the depth of the node,
     * avoiding loading those nodes that are already loaded
     *
     * @TODO - this setup currently assumes we are already in the node tree of the passed through node
     * it currently doesnt handle an entirely new node tree
     *
     * @param { object }
     */
    async function loadAllChildren (node) {
        const breadcrumbs = getBreadcrumbs(node, []);
        breadcrumbs.push( { id: node.id, name: node.name } ); //add in the actual node as the last
        const viewNodesStore = useViewNodesStore();
        const activeBreadcrumbs = [ ...viewNodesStore.breadcrumbs, { id: viewNodesStore.current.id } ];
        const onlyInactiveBreadcrumbs = breadcrumbs.filter((newBreadcrumb) => { //to only load the nodes that aren't already active
            return !activeBreadcrumbs.some((activeBreadcrumb) => {
                return newBreadcrumb.id === activeBreadcrumb.id; // return the ones with equal id
            });
        });
        for (let i = 0; i < onlyInactiveBreadcrumbs.length; i++) { //recursively load in each node and traverse down
            await useViewNodesStore().setNextSelected(onlyInactiveBreadcrumbs[i].id);
        }
    }

    /**
     * Load in another level of children of the target node id
     *
     * @param { number }
     */
    async function loadNextChildren (id) {
        try {
            isLoading.value = true;
            const response = await fetch(`${useStore().getApiEndpoint()}/${id}?descendants=2&limit=50`, {
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            const responseJson = await response.json();
            if (response.ok) {
                const newNode = responseJson;
                newNode.children.forEach((node) => useSelectedNodesStore().checkIfSelected(node));
                const oldNode = findById(nodes.value.children, id); //grab the old node to update
                useSelectedNodesStore().checkIfSelected(oldNode);
                oldNode.children = newNode.children; //we just grab the loaded children and overwrite the old property
            } else {
                throw new Error(responseJson.title, { ...responseJson } );
            }
        } catch (error) {
            console.warn(error);
            hasLoadError.value = true;
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Update the nodes when a change has occured, either from the initial load or adding or deleting them
     *
     * @param { Object }
     */
    function updateNodes (updatedNodes) { //we add them to .children so the top level structure is consistent with the levels below
        nodes.value = {
            id: UNSET_ID,
            name: 'Curricula',
            children: updatedNodes,
            enabled: true,
        }
        updatedNodes.forEach((node) => useSelectedNodesStore().checkIfSelected(node)); //ensure isSelected is kept up to date
    }

    /**
     * A new curriculum has been added so add it to the nodes
     *
     * @param { object }
     */
    function addNewCurriculum (curriculum) {
        nodes.value.children.push(curriculum);
        sortByName(nodes.value.children);
    }

    /**
     * A node has been updated, so we should check if the name change would prompt a sort change
     *
     * @param { object }
     */
    function nodeUpdated (node) {
        if (node.parent) { //sort via the parent node
            const parentNode = findById(nodes.value.children, node.parent.id);
            sortByName(parentNode.children);
        } else { //otherwise sort on the children
            sortByName(nodes.value.children);
        }
    }

    /**
     * Pull the node out of the list
     *
     * @param { object }
     */
    function deleteNode (node) {
        useViewNodesStore().checkDeletedNode(node); //check we aren't on the deleted node currently, if so we could move traverse backwards
        const nodeRemoved = removeId(nodes.value.children, node.id);
        updateNodes(nodeRemoved);
    }

    return {
        nodes,
        isLoading,
        hasLoadError,
        hasNoCurriculum,
        hasSingleCurriculum,
        load,
        loadId,
        loadNextChildren,
        addNewCurriculum,
        nodeUpdated,
        deleteNode
    }

});