/**
 * Stores all the curriculum codes and determines display of nodes
 */

import { defineStore } from 'pinia';
import { useActiveCodesStore } from './mcActiveCodesStore';


export const useCodesStore = defineStore('mcCodesStore', {

    state: () => ({
        codes: {}, //all the codes
    }),

    actions: {

        /**
         * Add in all the codes for display
         *
         * @param { Array }
         */
        load (codes) {
            this.codes = {
                children: codes,
            }
            const activeCodeStore = useActiveCodesStore();
            activeCodeStore.reset();
            if (activeCodeStore.hasSingleGroup) {
                activeCodeStore.setSelectedNode(0, this.codes.children[0].id);
            }
        },
    },

});