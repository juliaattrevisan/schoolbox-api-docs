/**
 * Stores all the curriculum codes and determines display of nodes
 */

import { defineStore } from 'pinia';
import { useStore, apiEndpoint } from './manageCurriculumStore';
import { useViewNodesStore } from '../viewNodes/store/mcViewNodesStore';
import { useSelectedNodesStore } from '../selectedNodes/store/mcSelectedNodesStore';

export const UNSET_ID = 0;
const SB_CURRICULUM_TOP_LEVEL = 'SB_CURRICULUM_TOP_LEVEL'; //to id to store the top level session storage with


export const useNodesStore = defineStore('mcNodes', {

    state: () => ({
        nodes: {}, //all the nodes currently loaded
        isLoading: true, //to display loading note
        hasLoadError: false, //to display loading error
        isProcessing: false, //to notify that an action is taking place
    }),

    actions: {

        /**
         * check if the top level is loaded in the session storage already
         * so we don't reload
         */
        initTopLevel () {
            /**
             * @TODO - Hold off on this for now, I'll need to think a bit more on what to store in the session storage
             * before I enable this.
             */
            // if (sessionStorage.getItem(SB_CURRICULUM_TOP_LEVEL)) {
            //     this.getTopLevel();
            // } else {
            //     this.loadTopLevel();
            // }
            this.loadTopLevel();
        },

        /**
         * get the top level from session storage
         */
        async getTopLevel () {
            const topLevelNodes = JSON.parse(sessionStorage.getItem(SB_CURRICULUM_TOP_LEVEL)); //convert the saved string var back to JSON
            await this.setTopLevel(topLevelNodes);
            const store = useStore();
            store.isLoading = false;
        },

        /**
         * load in the top level nodes from the api
         */
        async loadTopLevel () {
            const store = useStore();
            try {
                const response = await fetch(`${apiEndpoint}/?topLevelOnly=1`);
                if (response.ok) {
                    const topLevelNodes = await response.json();
                    sessionStorage.setItem(SB_CURRICULUM_TOP_LEVEL, JSON.stringify(topLevelNodes)); //session storage vars need to be saved as strings only
                    await this.setTopLevel(topLevelNodes);
                } else {
                    console.warn(response);
                    store.hasLoadError = true; //show the load error display state
                }
            } catch (error) {
                console.warn(error);
                store.hasLoadError = true; //show the load error display state
            } finally {
                store.isLoading = false; //otherwise show the tree
            }
        },

        /**
         * set the top level nodes, passed through either from loading or
         * the already stored session storage
         *
         * @param { object }
         */
        async setTopLevel (nodes) {
            this.updateNodes(nodes);
            const viewNodesStore = useViewNodesStore();
            if (viewNodesStore.hasSingleCurriculum) { //we jump straight to the only node available if there's only one
                const singleCurriculumId = this.nodes.children[0].id;
                await this.loadChildren(singleCurriculumId); //load in its children for initial display
                viewNodesStore.setSelected(1, singleCurriculumId); //then select the node
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
                    newNode.children.forEach((node) => useSelectedNodesStore().addIsSelected(node));
                    const oldNode = findById(this.nodes.children, id);
                    useSelectedNodesStore().addIsSelected(oldNode);
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
                name: 'Curricula',
                children: nodes,
                enabled: true,
            }
            nodes.forEach((node) => useSelectedNodesStore().addIsSelected(node));
        },

        /**
         * A new curriculum has been added so add it to the nodes
         *
         * @param { object }
         */
        addNewCurriculum (curriculum) {
            this.nodes.children.push(curriculum);
            sortByName(this.nodes.children);
        },

        /**
         * A node has been updated, so we should check if the name change would prompt a sort change
         *
         * @param { object }
         */
        nodeUpdated (node) {
            const parentNode = findById(this.nodes.children, node.parent.id);
            sortByName(parentNode.children);
        },

    },

});

/**
 * To just return the node containing the specified id
 * We recursively loop through the object until we find the
 * matched object or undefined if not found
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

/**
 * Sort the passed through curriculum nodes by their .name property
 *
 * @param { array }
 */
 export function sortByName (array, id) {
    array.sort((a, b) => {
        return a.name.localeCompare(b.name, undefined, {
            numeric: true,
            sensitivity: 'base'
        });
    });
}