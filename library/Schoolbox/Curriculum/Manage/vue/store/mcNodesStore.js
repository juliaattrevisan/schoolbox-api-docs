/**
 * Stores all the curriculum codes and determines display of nodes
 */

import { defineStore } from 'pinia';
import { useViewNodesStore } from './mcViewNodesStore';


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
            const activeNodeStore = useViewNodesStore();
            activeNodeStore.reset();
            if (activeNodeStore.hasSingleCurriculum) {
                activeNodeStore.setSelected(0, this.nodes.children[0].id);
            }
        },
    },

});