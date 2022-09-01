/**
 * Stores all the curriculum codes and determines display of nodes
 */

import { defineStore } from 'pinia';
import { useStore, apiEndpoint } from './manageCurriculumStore';
import { useViewNodesStore } from '../viewNodes/store/mcViewNodesStore';
import { useActiveNodeStore } from '../activeNode/store/mcActiveNodeStore';

export const UNSET_ID = 0;


export const useNodesStore = defineStore('mcNodes', {

    state: () => ({
        nodes: {}, //all the nodes currently loaded
        isLoading: true, //to display loading note
        hasLoadError: false, //to display loading error
        isProcessing: false, //to notify that an action is taking place
    }),

    actions: {

        /**
         * Add in all the top level nodes for display
         */
        async loadTopLevel () {

            const store = useStore();
            try {
                const response = await fetch(`${apiEndpoint}/?topLevelOnly=1`);
                if (response.ok) {
                    const nodes = await response.json();
                    this.updateNodes(nodes);
                    const viewNodesStore = useViewNodesStore();
                    if (viewNodesStore.hasSingleCurriculum) { //we jump straight to the only node available if there's only one
                        const singleCurriculumId = this.nodes.children[0].id;
                        await this.loadChildren(singleCurriculumId); //load in its children for initial display
                        viewNodesStore.setSelected(1, singleCurriculumId); //then select the node
                    }
                } else {
                    console.warn(response);
                    store.hasLoadError = true; //show the load error display state
                }
            } catch (error) {
                console.warn(error);
                store.hasLoadError = true; //show the load error display state
            } finally {
                store.isLoading = false; //otherwise show the table
            }
        },

        /**
         * Load in another level of children of the target node
         *
         * @param { number }
         */
        async loadChildren (id) {
            const oldNode = findById(this.nodes.children, id);
            if (oldNode.children !== null) { //we don't need to load them again
                return;
            }
            try {
                const response = await fetch(`${apiEndpoint}/${id}?descendants=1`);
                if (response.ok) {
                    const newNode = await response.json();
                    const oldNode = findById(this.nodes.children, id);
                    oldNode.children = newNode.children; //we just grab the loaded children and overwrite the old property that was previously null
                } else {
                    console.warn(response);
                    this.hasLoadError = true;
                }
            } catch (error) {
                console.warn(error);
                this.hasLoadError = true;
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Update the nodes when a change has occured, either from the initial load or adding or deleting them
         *
         * @param { Object }
         */
        updateNodes (nodes) { //we add them to .children so the top level structure is consistent with the levels below
            this.nodes = {
                id: UNSET_ID,
                name: 'Curriculums',
                children: nodes,
                enabled: true,
            }
        },

        /**
         * A new curriculum has been added so add it to the nodes
         *
         * @param { object }
         */
        addNewCurriculum (curriculum) {
            this.nodes.children.push(curriculum);
        },

        /**
         * Add in a new subsection to the active node
         *
         * @param { object }
         */
        async addSubsection (newNode) {
            const activeNodeStore = useActiveNodeStore();
            if (activeNodeStore.node.children === null) { //if the children aren't loaded, load them in - which will also return the newly added node
                await this.loadChildren(activeNodeStore.node.id);
            } else { //otherwise we need to push the newNode in as the children are already loaded
                activeNodeStore.node.children.push(newNode);
            }
            const viewNodesStore = useViewNodesStore();
            if (viewNodesStore.current.id !== newNode.parent.id) { //if we aren't on the new nodes level move down so we can see the new node
                viewNodesStore.setNextSelected(newNode.parent.id);
            }
        },

        /**
         * Pull the active node out of the list
         */
        deleteNode () {
            const activeNodeStore = useActiveNodeStore();
            const activeNodeRemoved = removeId(this.nodes.children, activeNodeStore.node.id);
            this.updateNodes(activeNodeRemoved);
            activeNodeStore.unset();
        },

    },

});

/**
 * To return an array with any objects containing the specified id removed
 * we move through the object, looping recursively through .children and remove
 * any with the id that's being removed
 *
 * @param { array }
 * @param { number }
 *
 * @returns { object }
 */
export function removeId (data, id) {
    if (data !== null) { // .children wont always be available if the node hasn't been loaded yet
        return data.reduce((previousValue, currentValue) => {
            if (currentValue.id !== id) {
                previousValue.push( { ...currentValue, children: removeId(currentValue.children, id) } );
            }
            return previousValue;
        }, []);
    } else {
        return data;
    }
}

/**
 * To just return the node containing the specified id
 *
 * @param { array }
 * @param { number }
 */
export function findById (array, id) {
    for (const item of array) {
        if (item.id === id) {
            return item;
        }
        if (item.children?.length) {
            const innerResult = findById(item.children, id);
            if (innerResult) {
                return innerResult;
            }
        }
    }
}