/**
 * Stores all the curriculum nodes and load in subsequent children
 */

import { defineStore } from "pinia";
import { UNSET_ID, findById, getBreadcrumbs } from "@schoolboxCurriculum/js/nodeUtils";
import { useStore, apiEndpoint } from "./curriculumSelectorStore";
import { useModalViewNodesStore } from "./csModalViewNodesStore";
import { useModalSelectedNodesStore } from "./csModalSelectedNodesStore";
import {
    hasStoredNodes,
    isStoringNodes,
    setIsStoringNodes,
    getStoredNodes,
    setStoredNodes,
    NODES_STORED,
} from "@schoolboxCurriculum/js/globalStorage";

export const useNodesStore = defineStore(`csNodes`, {
    state: () => ({
        nodes: {}, // the root node, containing all the children loaded
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
        hasNoCurriculum() {
            return this.nodes.children && this.nodes.children.length === 0 ? true : false;
        },

        /**
         * If there's only one curriculum, we can select it
         * straight away, otherwise we allow the user to make the selection
         *
         * @return { Boolean }
         */
        hasSingleCurriculum() {
            return this.nodes.children && this.nodes.children.length === 1 ? true : false;
        },
    },

    actions: {
        /**
         * load in the top level nodes from the api
         */
        async load() {
            const store = useStore();
            try {
                if (hasStoredNodes()) {
                    // if we have the root nodes stored in global storage, load them
                    this.loadStored();
                } else if (isStoringNodes()) {
                    // if we've already requested the root nodes, don't request them again
                    document.addEventListener(NODES_STORED, () => {
                        this.loadStored();
                    });
                } else {
                    setIsStoringNodes();
                    // otherwise load them from the api
                    await this.loadRemote();
                    store.isLoading = false;
                }
            } catch (error) {
                console.warn(error);
                store.hasLoadError = true; // show the load error display state
            }
        },

        /**
         * load in the top level nodes from the global storage
         */
        async loadStored() {
            this.nodes = getStoredNodes();
            if (this.hasSingleCurriculum) {
                const singleCurriculumId = this.nodes.children[0].id;
                const modalViewNodesStore = useModalViewNodesStore();
                modalViewNodesStore.setSelected(modalViewNodesStore.currentDepth + 1, singleCurriculumId);
            }
            useStore().isLoading = false;
        },

        /**
         * load in the top level nodes from the api
         */
        async loadRemote() {
            const store = useStore();
            try {
                const response = await fetch(`${apiEndpoint}/?topLevelOnly=1&enabled=1&descendants=1&limit=50`);
                if (response.ok) {
                    const topLevelNodes = await response.json();
                    await this.setTopLevel(topLevelNodes);
                    setStoredNodes(this.nodes); // store the root nodes in global storage
                } else {
                    console.warn(response);
                    store.hasLoadError = true; //show the load error display state
                }
            } catch (error) {
                console.warn(error);
                store.hasLoadError = true; //show the load error display state
            }
        },

        /**
         * load the single curriculum for display straight away
         */
        async loadSingleCurriculum() {
            if (this.nodes?.children !== null) {
                const singleCurriculumId = this.nodes?.children[0]?.id;
                await useModalViewNodesStore().setNextSelected(singleCurriculumId);
            }
        },

        /**
         * load the node, retrieving all its parents, so we can traverse directly to it
         *
         * @param { number } id - the id of the Node to load
         */
        async loadId(id) {
            try {
                this.isLoading = true;
                const response = await fetch(`${apiEndpoint}/${id}?ancestors=99`); // just use 99 as hopefully there's no tree that deep...
                if (response.ok) {
                    const nodeWithParents = await response.json();
                    await this.loadAllChildren(nodeWithParents);
                } else {
                    // useStore().hasId = false; // as the id couldn't be found
                    if (this.hasSingleCurriculum) {
                        // we should still jump straight to the only node available if there's only one
                        await this.loadSingleCurriculum();
                    }
                    if (response.status === 404) {
                        // and we assume the id can't be found, displaying an error
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
         * to only load the nodes that aren't already active
         *
         * @param { Breadcrumb[] } activeBreadcrumbs - the active breadcrumbs
         * @param { Breadcrumb } newBreadcrumb - breadcrumb to add
         * @returns { boolean } whether the breadcrumb is active
         */
        isActiveBreadcrumb(activeBreadcrumbs, newBreadcrumb) {
            return !activeBreadcrumbs.some((activeBreadcrumb) => newBreadcrumb.id === activeBreadcrumb.id);
        },

        /**
         * load in all the children required to reach the depth of the node,
         * avoiding loading those nodes that are already loaded
         *
         * @param { Node } node - the Node to load all children of
         */
        async loadAllChildren(node) {
            const breadcrumbs = getBreadcrumbs(node, []);
            breadcrumbs.push({ id: node.id, name: node.name }); // add in the actual node as the last
            const modalViewNodesStore = useModalViewNodesStore();
            const activeBreadcrumbs = [
                ...modalViewNodesStore.breadcrumbs,
                { id: modalViewNodesStore.current.id, name: modalViewNodesStore.current.name },
            ];
            const onlyInactiveBreadcrumbs = breadcrumbs.filter((newBreadcrumb) =>
                this.isActiveBreadcrumb(activeBreadcrumbs, newBreadcrumb),
            );
            for (let i = 0; i < onlyInactiveBreadcrumbs.length; i += 1) {
                // recursively load in each node and traverse down
                await modalViewNodesStore.setNextSelected(onlyInactiveBreadcrumbs[i].id);
            }
        },

        /**
         * set the top level nodes, passed through either from loading or
         * the already stored global storage
         *
         * @param { object }
         */
        async setTopLevel(nodes) {
            this.updateNodes(nodes);
            if (this.hasSingleCurriculum) {
                //we jump straight to the only node available if there's only one
                const singleCurriculumId = this.nodes.children[0].id;
                await useModalViewNodesStore().setNextSelected(singleCurriculumId); //load in its children for initial display
            }
        },

        /**
         * Load in another level of children of the target node
         *
         * @param { number }
         */
        async loadChildren(id) {
            try {
                this.isLoading = true;
                const descendants = useStore().includeChildren ? 10 : 2; // if we're including children, load in all descendants, otherwise we just load in the next level to show the structure
                const response = await fetch(`${apiEndpoint}/${id}?descendants=${descendants}&limit=50`);
                if (response.ok) {
                    const newNode = await response.json();
                    newNode.children?.forEach((node) => useModalSelectedNodesStore().checkIfSelected(node));
                    const newNodeChildrenEnabled = newNode.children.filter((node) => node.enabled); //only show those nodes with children enabled, this can be handled with enabled=1 on just topLevelOnly
                    const oldNode = findById(this.nodes.children, id);
                    useModalSelectedNodesStore().checkIfSelected(oldNode);
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
         * Update the nodes when a change has occurred, either from the initial load or adding or deleting them
         *
         * @param { Object }
         */
        updateNodes(nodes) {
            //we add them to .children so the top level structure is consistent with the levels below
            this.nodes = {
                id: UNSET_ID,
                name: "Curricula",
                children: nodes,
                enabled: true,
            };
            nodes.forEach((node) => useModalSelectedNodesStore().checkIfSelected(node)); // ensure isSelected is kept up to date
        },
    },
});
