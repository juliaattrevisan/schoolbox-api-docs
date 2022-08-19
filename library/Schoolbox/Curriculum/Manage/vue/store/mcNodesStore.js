/**
 * Stores all the curriculum codes and determines display of nodes
 */

import { defineStore } from 'pinia';
import { useActiveNodesStore } from './mcActiveNodesStore';


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
            this.nodes = {
                children: nodes,
            }
            const activeNodeStore = useActiveNodesStore();
            activeNodeStore.reset();
            if (activeNodeStore.hasSingleCurriculum) {
                activeNodeStore.setSelected(0, this.nodes.children[0].id);
            }
        },
    },

});