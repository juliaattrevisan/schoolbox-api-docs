/**
 * Stores all the curriculum codes and determines display of nodes
 */

import { defineStore } from 'pinia';
import { useStore } from './manageCurriculumStore';
import { useViewNodesStore } from '../viewNodes/store/mcViewNodesStore';
import { useActiveNodeStore } from '../activeNode/store/mcActiveNodeStore';

export const UNSET_ID = 0;
export let apiEndpoint = '';


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
         *
         * @param { Strng }
         */
        async loadTopLevel (endpoint) {
            apiEndpoint = endpoint;
            const store = useStore();
            try {
                const response = await fetch(`${apiEndpoint}?topLevelOnly=1`);
                if (response.ok) {
                    const nodes = await response.json();
                    this.updateNodes(nodes);
                    const viewNodesStore = useViewNodesStore();
                    if (viewNodesStore.hasSingleCurriculum) { //we jump straight to the only node available if there's only one
                        viewNodesStore.setSelected(1, this.nodes.children[0].id);
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
                const response = await fetch(`${apiEndpoint}${id}?descendants=1`);
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
         * Pull the active node out of the list
         */
        deleteActiveNode () {
            const activeNodeStore = useActiveNodeStore();
            const activeNodeRemoved = removeId(this.nodes.children, activeNodeStore.node.id);
            this.updateNodes(activeNodeRemoved);
            activeNodeStore.unset();
        },

    },

});

/**
 * To return an array with any objects containing the specified id removed
 *
 * @param { array }
 * @param { number }
 *
 * @returns { object }
 */
export function removeId (data, id) {
    return data.reduce((previousValue, currentValue) => {
        if (currentValue.id !== id) {
            previousValue.push( { ...currentValue, children: removeId(currentValue.children, id) } );
        }
        return previousValue;
    }, []);
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