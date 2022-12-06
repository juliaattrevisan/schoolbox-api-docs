/**
 * Stores all the curriculum codes and determines display of nodes
 */

import { defineStore } from 'pinia';
import { UNSET_ID, findById, sortNodes, removeId, getBreadcrumbs } from '@schoolboxCurriculum/js/nodeUtils';
import { useStore, apiEndpoint } from './manageCurriculumStore';
import { useViewNodesStore } from '../viewNodes/store/mcViewNodesStore';
import { useSelectedNodesStore } from '../selectedNodes/store/mcSelectedNodesStore';


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
        async load () {
            const store = useStore();
            try {
                const response = await fetch(`${apiEndpoint}/?topLevelOnly=1&descendants=1&limit=50`, {
                    headers: {
                        'Content-Type': 'application/json',
                    }
                });
                if (response.ok) {
                    const topLevelNodes = await response.json();
                    await this.setTopLevel(topLevelNodes);
                } else {
                    store.hasLoadError = true; //show the load error display state
                }
            } catch (error) {
                console.warn(error);
            } finally {
                store.isLoading = false;
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
                await this.loadId(store.id);
            } else if (this.hasSingleCurriculum) { //we jump straight to the only node available if there's only one
                await this.loadSingleCurriculum();
            }
        },

        /**
         * load the single curriculum for display straight away
         */
        async loadSingleCurriculum () {
            const singleCurriculumId = this.nodes.children[0].id;
            await useViewNodesStore().setNextSelected(singleCurriculumId);
        },

        /**
         * load the node, retrieving all its parents so we can traverse directly to it
         *
         * @param { number }
         */
        async loadId (id) {
            try {
                this.isLoading = true;
                const response = await fetch(`${apiEndpoint}/${id}?ancestors=99`); //just use 99 as hopefully there's no tree that deep...
                if (response.ok) {
                    const nodeWithParents = await response.json();
                    await this.loadAllChildren(nodeWithParents);
                } else {
                    this.hasId = false; //as the id couldn't be found
                    if (this.hasSingleCurriculum) { //we should still jump straight to the only node available if there's only one
                        await this.loadSingleCurriculum();
                    }
                    if (response.status === 404) { //and we assume the id can't be found, displaying an error
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
         * load in all the children required to reach the depth of the node,
         * avoiding loading those nodes that are already loaded
         *
         * @TODO - this setup currently assumes we are already in the node tree of the passed through node
         * it currently doesnt handle an entirely new node tree
         *
         * @param { object }
         */
        async loadAllChildren (node) {
            const breadcrumbs = getBreadcrumbs(node, []);
            breadcrumbs.push( { id: node.id, name: node.name } ); //add in the actual node as the last
            const viewNodesStore = useViewNodesStore();
            const activeBreadcrumbs = [ ...viewNodesStore.breadcrumbs, { id: viewNodesStore.current.id } ];
            const onlyInactiveBreadcrumbs = breadcrumbs.filter((newBreadcrumb) => { //to only load the nodes that aren't already active
                return !activeBreadcrumbs.some((activeBreadcrumb) => {
                    return newBreadcrumb.id === activeBreadcrumb.id; // return the ones with equal id
               });
            });
            for (let i = 0; i < onlyInactiveBreadcrumbs.length; i++) { //recursively load in each node and traverse down
                await useViewNodesStore().setNextSelected(onlyInactiveBreadcrumbs[i].id);
            }
        },

        /**
         * Load in another level of children of the target node id
         *
         * @param { number }
         */
        async loadNextChildren (id) {
            try {
                this.isLoading = true;
                const response = await fetch(`${apiEndpoint}/${id}?descendants=2&limit=50`, {
                    headers: {
                        'Content-Type': 'application/json',
                    }
                });
                const responseJson = await response.json();
                if (response.ok) {
                    const newNode = responseJson;
                    newNode.children.forEach((node) => useSelectedNodesStore().checkIfSelected(node));
                    const oldNode = findById(this.nodes.children, id); //grab the old node to update
                    useSelectedNodesStore().checkIfSelected(oldNode);
                    oldNode.children = newNode.children; //we just grab the loaded children and overwrite the old property
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
            nodes.forEach((node) => useSelectedNodesStore().checkIfSelected(node)); //ensure isSelected is kept up to date
        },

        /**
         * A new curriculum has been added so add it to the nodes
         *
         * @param { object }
         */
        addNewCurriculum (curriculum) {
            this.nodes.children.push(curriculum);
            sortNodes(this.nodes.children);
        },

        /**
         * A node has been updated, so we should check if the name change would prompt a sort change
         *
         * @param { object }
         */
        nodeUpdated (node) {
            if (node.parent) { //sort via the parent node
                const parentNode = findById(this.nodes.children, node.parent.id);
                sortNodes(parentNode.children);
            } else { //otherwise sort on the children
                sortNodes(this.nodes.children);
            }
        },

        /**
         * Pull the node out of the list
         *
         * @param { object }
         */
        deleteNode (node) {
            useViewNodesStore().checkDeletedNode(node); //check we aren't on the deleted node currently, if so we could move traverse backwards
            const nodeRemoved = removeId(this.nodes.children, node.id);
            this.updateNodes(nodeRemoved);
        },

    },

});