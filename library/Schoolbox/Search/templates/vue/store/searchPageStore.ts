/**
 * Handles any core properties for the search page
 */

import { defineStore } from "pinia";
import { useFilterTypesStore, FilterType } from "../filters/store/spFilterTypesStore";

export const useStore = defineStore("searchPage", () => {
    const endpoint = window.Vue.ref(""); // to display loading note

    /**
     * When switching between stories, we need to reset the data models
     * as they persist between the stories otherwise
     */
    function checkForStorybook() {
        if (typeof window.STORYBOOK_ENV !== "undefined") {
            // useViewNodesStore().$reset();
            // useActiveNodeStore().$reset();
        }
    }

    /**
     * store any passed through properties
     * @param {string} $endpoint - the search api url
     * @param {FilterType[]} $filterTypes - the filter types
     */
    function init($endpoint: string, $filterTypes: FilterType[]) {
        checkForStorybook();
        endpoint.value = $endpoint;
        useFilterTypesStore().allTypes = $filterTypes;
    }

    return {
        endpoint,
        init,
    };
});
