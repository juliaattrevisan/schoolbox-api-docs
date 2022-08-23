/**
 * Stores all the curriculum codes and determines display of nodes
 */

import { defineStore } from 'pinia';
import { useActiveNodeStore } from '../activeNode/store/mcActiveNodeStore';


export const useNodesStore = defineStore('mcNodesStore', {

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
        },

        /**
         * Update the nodes when a change has occured, either from the initial load or adding or deleting them
         *
         * @param { Object }
         */
        updateNodes (nodes) { //we add them to .children so the top level structure is consistent with the levels below
            this.nodes = {
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
function removeId (data, id) {
    return data.reduce((previousValue, currentValue) => {
        if (currentValue.id !== id) {
            previousValue.push( { ...currentValue, children: removeId(currentValue.children, id) } );
        }
        return previousValue;
    }, []);
}