/**
 * Handles any core properties for the search page
 */

import { defineStore } from "pinia";
import { ref } from "vue";

export const useStore = defineStore("searchPage", () => {
    const endpoint = ref(""); // to display loading note

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
     */
    function init($endpoint: string) {
        checkForStorybook();
        endpoint.value = $endpoint;
    }

    return {
        endpoint,
        init,
    };
});
