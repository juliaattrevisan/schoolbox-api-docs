/**
 * Stores all the curriculum codes and load in subsequent children
 */

import { defineStore } from 'pinia';
import { useStore, apiEndpoint } from './curriculumSelectorStore';
import { useModalActiveCodesStore } from '../store/csModalActiveCodesStore';

export const UNSET_ID = 0;


export const useCodesStore = defineStore(`csCodes`, {

    state: () => ({
        codes: [], //all the codes currenty loaded
        isLoading: false, //to display loading note
        hasLoadError: false, //to display loading error
        isProcessing: false, //to notify that an action is taking place
    }),

    getters: {

        /**
         * If there's only no Curriculum, display an empty state
         *
         * @return { Boolean }
         */
        hasNoCurriculum () {
            return this.codes.children && this.codes.children.length === 0 ? true : false;
        },

        /**
         * If there's only one curriculum, we can select it
         * straight away, otherwise we allow the user to make the selection
         *
         * @return { Boolean }
         */
        hasSingleCurriculum () {
            return this.codes.children && this.codes.children.length === 1 ? true : false;
        },

    },

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
                const response = await fetch(`${apiEndpoint}/?topLevelOnly=1&enabled=1&descendants=1`);
                if (response.ok) {
                    const topLevelNodes = await response.json();
                    // sessionStorage.setItem(SB_CURRICULUM_TOP_LEVEL, JSON.stringify(topLevelNodes)); //session storage vars need to be saved as strings only
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
            if (this.hasSingleCurriculum) { //we jump straight to the only node available if there's only one
                const singleCurriculumId = this.codes.children[0].id;
                await useModalActiveCodesStore().setNextSelected(singleCurriculumId); //load in its children for initial display
            }
        },

        /**
         * Load in another level of children of the target node
         *
         * @param { number }
         */
        async loadChildren (id) {
            try {
                this.isLoading = true;
                const response = await fetch(`${apiEndpoint}/${id}?descendants=2`); //we go two levels deep so we can check for children on two levels
                if (response.ok) {
                    const newNode = await response.json();
                    const newNodeChildrenEnabled = newNode.children.filter((node) => (node.enabled)); //only show those nodes with children enabled, this can be handled with enabled=1 on just topLevelOnly
                    const oldNode = findById(this.codes.children, id);
                    oldNode.children = newNodeChildrenEnabled; //we just grab the loaded children and overwrite the old property that was previously null
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
            this.codes = {
                id: UNSET_ID,
                name: 'Curricula',
                children: nodes,
                enabled: true,
            }
        },

    },

});

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

/**
 * Recursively loop through the parents adding in a breadcrumb for each
 *
 * @param { object }
 * @param { array }
 */
export function getBreadcrumbs (node, currentBreadcrumbs) {
    if (node.hasOwnProperty('parent') && node.parent !== null) { //if there's a parent to use as a breadcrumb
        let breadcrumbs = [ { id: node.parent.id, name: node.parent.name }, ...currentBreadcrumbs ]; //add it in to the front of the list
        return breadcrumbs = getBreadcrumbs(node.parent, breadcrumbs); //and then check the next node up the list
    } else { //otherwise we must be at the top of the breadcrumb list
        return currentBreadcrumbs; //so we can return now
    }
}