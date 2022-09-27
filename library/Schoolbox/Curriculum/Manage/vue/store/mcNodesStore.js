/**
 * Stores all the curriculum codes and determines display of nodes
 */

import { defineStore } from 'pinia';
import { useStore, apiEndpoint } from './manageCurriculumStore';
import { useViewNodesStore } from '../viewNodes/store/mcViewNodesStore';
import { useSelectedNodesStore } from '../selectedNodes/store/mcSelectedNodesStore';


export const UNSET_ID = 0;


export const useNodesStore = defineStore('mcNodes', {

    state: () => ({
        nodes: {}, //all the nodes currently loaded
        isLoading: false, //to display loading note
        hasLoadError: false, //to display loading error
    }),

    getters: {

        /**
         * If there's only no Curriculum, display an empty state
         *
         * @return { Boolean }
         */
        hasNoCurriculum () {
            return this.nodes.children && this.nodes.children.length === 0 ? true : false;
        },

        /**
         * If there's only one curriculum, we can select it
         * straight away, otherwise we allow the user to make the selection
         *
         * @return { Boolean }
         */
        hasSingleCurriculum () {
            return this.nodes.children && this.nodes.children.length === 1 ? true : false;
        },

    },

    actions: {

        /**
         * load in the top level nodes from the api
         */
        async loadTopLevel () {
            const store = useStore();
            try {
                const response = await fetch(`${apiEndpoint}/?topLevelOnly=1&descendants=1`, {
                    headers: {
                        'Content-Type': 'application/json',
                    }
                });
                if (response.ok) {
                    const topLevelNodes = await response.json();
                    await this.setTopLevel(topLevelNodes);
                } else if (response.status === 401) {
                    store.hasPermissionDeniedError = true; //show a permission denied error
                } else {
                    store.hasLoadError = true; //show the load error display state
                }
            } catch (error) {
                store.hasLoadError = true; //show the load error display state
                throw new Error(error.message);
            } finally {
                store.isLoading = false; //otherwise show the tree
            }
        },

        /**
         * set the top level nodes, passed through from loading
         *
         * @param { object }
         */
        async setTopLevel (nodes) {
            this.updateNodes(nodes);
            const store = useStore();
            if (store.hasId) {
                this.loadId(store.id);
            } else if (this.hasSingleCurriculum) { //we jump straight to the only node available if there's only one
                this.loadSingleCurriculum();
            }
        },

        /**
         *
         */
        async loadSingleCurriculum () {
            const singleCurriculumId = this.nodes.children[0].id;
            await this.loadChildren(singleCurriculumId); //load in its children for initial display
            useViewNodesStore().setSelected(1, singleCurriculumId); //then select the node
        },

        /**
         *
         *
         * @param { number }
         */
        async loadId (id) {
            try {
                this.isLoading = true;
                const response = await fetch(`${apiEndpoint}/${id}?ancestors=99`); //just use 99 as hopefully there's no tree that deep...
                if (response.ok) {
                    const nodeWithParents = await response.json();
                    node.breadcrumbs = getBreadcrumbs(nodeWithParents, []);
                    console.warn(node.breadcrumbs);
                } else {
                    this.hasId = false;
                    if (this.hasSingleCurriculum) { //we jump straight to the only node available if there's only one
                        this.loadSingleCurriculum();
                    }
                    if (response.status === 404) { //we assume the id can't be found
                        throw new Error(`No node id matching ${id} has been found`);
                    }
                }
            } catch (error) {
                this.hasLoadError = true;
                throw new Error(error.message, error.options);
            } finally {
                this.isLoading = false;
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
                const response = await fetch(`${apiEndpoint}/${id}?descendants=2`, {
                    headers: {
                        'Content-Type': 'application/json',
                    }
                });
                const responseJson = await response.json();
                if (response.ok) {
                    const newNode = responseJson;
                    newNode.children.forEach((node) => useSelectedNodesStore().addIsSelected(node));
                    const oldNode = findById(this.nodes.children, id);
                    useSelectedNodesStore().addIsSelected(oldNode);
                    oldNode.children = newNode.children; //we just grab the loaded children and overwrite the old property that was previously null
                } else {
                    throw new Error(responseJson.title, { ...responseJson } );
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
            if (node.parent) {
                const parentNode = findById(this.nodes.children, node.parent.id);
                sortByName(parentNode.children);
            } else {
                sortByName(this.nodes.children);
            }
        },

        /**
         * Pull the node out of the list
         *
         * @param { object }
         */
        deleteNode (node) {
            useViewNodesStore().checkDeletedNode(node); //check we aren't on the deleted node currently
            const nodeRemoved = removeId(this.nodes.children, node.id);
            this.updateNodes(nodeRemoved);
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

/**
 * To return an array with any objects containing the specified id removed
 * we move through the object, looping recursively through .children and remove
 * any with the id that's being removed
 *
 * @param { array }
 * @param { number }
 *
 * @returns { object }
 */
function removeId (data, id) {
    if (data !== null) { // .children wont always be available if the node hasn't been loaded yet
        return data.reduce((previousValue, currentValue) => {
            if (currentValue.id !== id) {
                previousValue.push( { ...currentValue, children: removeId(currentValue.children, id) } );
            }
            return previousValue;
        }, []);
    } else {
        return data;
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