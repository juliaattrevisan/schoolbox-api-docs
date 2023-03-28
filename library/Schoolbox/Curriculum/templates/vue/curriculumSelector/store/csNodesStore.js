/**
 * Stores all the curriculum nodes and load in subsequent children
 */

import { defineStore } from "pinia";
import { UNSET_ID, findById } from "@schoolboxCurriculum/js/nodeUtils";
import { useStore, apiEndpoint } from "./curriculumSelectorStore";
import { useModalViewNodesStore } from "./csModalViewNodesStore";

export const useNodesStore = defineStore(`csNodes`, {
    state: () => ({
        nodes: [], //all the nodes currenty loaded
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
                const response = await fetch(`${apiEndpoint}/?topLevelOnly=1&enabled=1&descendants=1&limit=50`);
                if (response.ok) {
                    const topLevelNodes = await response.json();
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
                const response = await fetch(`${apiEndpoint}/${id}?descendants=2&limit=50`); //we go two levels deep so we can check for children on two levels
                if (response.ok) {
                    const newNode = await response.json();
                    const newNodeChildrenEnabled = newNode.children.filter((node) => node.enabled); //only show those nodes with children enabled, this can be handled with enabled=1 on just topLevelOnly
                    const oldNode = findById(this.nodes.children, id);
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
        updateNodes(nodes) {
            //we add them to .children so the top level structure is consistent with the levels below
            this.nodes = {
                id: UNSET_ID,
                name: "Curricula",
                children: nodes,
                enabled: true,
            };
        },
    },
});
