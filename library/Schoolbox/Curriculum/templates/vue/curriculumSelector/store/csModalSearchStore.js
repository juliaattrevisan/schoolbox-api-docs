/**
 * Store the current search results when searching from cs-modal-header-search-input
 */

import { defineStore } from "pinia";
import { apiEndpoint, useStore } from "./store";
import { useSelectedNodesStore } from "./csSelectedNodesStore";
import { useModalViewNodesStore } from "./csModalViewNodesStore";

const MIN_QUERY_LENGTH = 2; //the amount of characters typed before the search becomes active
export const DEBOUNCE_AMOUNT = 350; //the amount in ms to hold off on searching, to allow the user to keep typing

export const useModalSearchStore = defineStore(`csModalSearch`, {
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
            this.isLoading = true;
            try {
                const store = useStore();
                const modalViewNodesStore = useModalViewNodesStore();
                const response = await fetch(
                    `${apiEndpoint}?parentId=${modalViewNodesStore.current.id}&query=${this.query}&enabled=1&limit=50` +
                        (store.includeWithoutCode ? "&includeWithoutCode=1" : ""),
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
        },
    },
});
