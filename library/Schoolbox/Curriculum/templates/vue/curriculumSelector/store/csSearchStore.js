/**
 * Store the current search results when searching from cs-search-input
 */

import { defineStore } from "pinia";
import { useStore, apiEndpoint } from "./curriculumSelectorStore";
import { useSelectedNodesStore } from "./csSelectedNodesStore";

const MIN_QUERY_LENGTH = 2; //the amount of characters typed before the search becomes active
export const DEBOUNCE_AMOUNT = 350; //the amount in ms to hold off on searching, to allow the user to keep typing

export const useSearchStore = defineStore(`csSearch`, {
    state: () => ({
        isLoading: false, //to notify that an action is taking place,
        query: "", //current search term to filter the active nodes
        results: [], //nodes returned in the current search results
    }),

    getters: {
        /**
         * To only start showing search results once enough characters
         * have been added
         *
         * @return { Boolean }
         */
        isActive() {
            return this.query.length >= MIN_QUERY_LENGTH;
        },

        /**
         * add any filters, should we be showing existing results?
         *
         * @return { array }
         */
        filteredResults() {
            if (this.isActive) {
                return this.results;
            } else {
                return [];
            }
        },

        /**
         * To show that no nodes match the current search
         *
         * @return { Boolean }
         */
        hasNoResults() {
            return this.isActive && this.filteredResults.length === 0 && !this.isLoading;
        },
    },

    actions: {
        /**
         * when the search isActive, we should display the loading prompt straight away,
         * but the debounce holds on calling search() until we assume the user has stopped typing
         */
        enableSearch() {
            this.isLoading = true;
        },

        /**
         * search for nodes that match
         */
        async search() {
            try {
                const store = useStore();
                const includeWithoutCode = store.includeWithoutCode ? 1 : 0; // search for nodes without a code
                const descendants = store.includeChildren ? 10 : 0; // if we're including children, load in all descendants, otherwise we just load in the next level to show the structure
                const response = await fetch(
                    `${apiEndpoint}?query=${this.query}&enabled=1&limit=50&includeWithoutCode=${includeWithoutCode}&descendants=${descendants}`,
                    {
                        method: "GET",
                        headers: {
                            "Content-Type": "application/json",
                        },
                    },
                );
                this.results = await response.json();
                const selectedNodesStore = useSelectedNodesStore();
                this.results.forEach((node) => (node.isSelected = selectedNodesStore.isSelected(node))); //ensure checked results are reflected
            } catch (error) {
                console.warn(error);
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * clear the current search
         */
        clear() {
            this.query = "";
            this.results = [];
            this.isLoading = false;
        },

        /**
         * add all found results to the selection
         */
        addAllResults() {
            const selectedNodesStore = useSelectedNodesStore();
            const newSelectedNodes = this.results.filter((newSelectedNode) => {
                //check if the node isn't in the list already
                return !selectedNodesStore.nodes.some((selectedNode) => selectedNode.id === newSelectedNode.id); //don't add in those that match
            });
            newSelectedNodes.forEach((newSelectedNode) => selectedNodesStore.add(newSelectedNode));
        },

        /**
         * remove all found results from the selection
         */
        removeAllResults() {
            const selectedNodesStore = useSelectedNodesStore();
            const matchedSelectedNodes = this.results.filter((matchedSelectedNode) => {
                //check if the node is in the list already
                return selectedNodesStore.nodes.some((selectedNode) => selectedNode.id === matchedSelectedNode.id); //add in those that match
            });
            selectedNodesStore.remove(matchedSelectedNodes);
        },
    },
});
