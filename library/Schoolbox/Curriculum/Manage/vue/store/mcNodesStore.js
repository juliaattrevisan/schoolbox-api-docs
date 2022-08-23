/**
 * Stores all the curriculum codes and determines display of nodes
 */

import { defineStore } from 'pinia';
import { useViewNodesStore } from './mcViewNodesStore';
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
            const nodesWithChildren = {
                children: nodes,
            }
            this.nodes = nodesWithChildren;
            // this.nodes = { //we add them to children so the top level structure is consistent with the levels below
            //     children: nodes,
            // }
            const activeNodeStore = useViewNodesStore();
            activeNodeStore.reset();
            if (activeNodeStore.hasSingleCurriculum) { //jump straight to the only available Curriculum if there's only one to choose from
                activeNodeStore.setSelected(0, this.nodes.children[0].id);
            }
        },

        /**
         *
         */
        deleteActiveNode () {
            const activeNode = useActiveNodeStore().node;
            const activeNodeRemoved = removeId(this.nodes.children, activeNode.id);
            const nodesWithChildren = {
                children: activeNodeRemoved,
            }
            this.nodes = nodesWithChildren;
            // this.nodes = {};
            // console.warn(activeNodeRemoved[0].children);
            // this.nodes = {
            //     children:
            // };
        },

    },

});

/**
 *
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