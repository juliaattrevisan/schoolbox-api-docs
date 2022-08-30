/**
 * Stores all the curriculum codes and determines display of nodes
 */

import { defineStore } from 'pinia';
import { useViewNodesStore } from '../store/mcViewNodesStore';
import { useActiveNodeStore } from '../activeNode/store/mcActiveNodeStore';

export const UNSET_ID = 0;


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
                name: 'Curriculum Packages',
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