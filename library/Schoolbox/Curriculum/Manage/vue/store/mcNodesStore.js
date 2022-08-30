/**
 * Stores all the curriculum codes and determines display of nodes
 */

import { defineStore } from 'pinia';
import { useViewNodesStore } from '../viewNodes/store/mcViewNodesStore';
import { useActiveNodeStore } from '../activeNode/store/mcActiveNodeStore';

export const UNSET_ID = 0;


export const useNodesStore = defineStore('mcNodes', {

    state: () => ({
        nodes: {}, //all the nodes currently loaded
    }),

    actions: {

        /**
         * Add in all the codes for display
         *
         * @param { Array }
         */
        load (nodes) {
            this.updateNodes(nodes);
            const viewNodesStore = useViewNodesStore();
            if (viewNodesStore.hasSingleCurriculum) { //we jump straight to the only node available if there's only one
                viewNodesStore.setSelected(1, this.nodes.children[0].id);
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
            }
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