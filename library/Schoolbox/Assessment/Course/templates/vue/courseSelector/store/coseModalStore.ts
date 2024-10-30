/**
 * To manage the state of the course selector modal
 */

import { defineStore } from "pinia";
import { useSearch } from "@schoolboxVue/composables/search/useSearch";
import type { Course } from "@schoolboxLibrary/types/Course";

export const useModalStore = defineStore(`coseModal`, () => {
    let endpoint = ""; // the endpoint to search for courses
    const isOpen = window.Vue.ref(false);
    const isFirstOpen = window.Vue.ref(true);
    const results = window.Vue.ref<Course[]>([]); // the results of the search
    const { query, isActive, isSearching, hasNoResults, hasError, search } = useSearch(endpoint, results, false); // to search for courses

    /**
     * Open the modal
     * @param $endpoint - the endpoint to search for courses
     */
    function init($endpoint: string): void {
        endpoint = $endpoint;
    }

    /**
     * Open the modal
     */
    function open(): void {
        isOpen.value = true;
        // load the courses if it's the first time the modal is opened, to avoid always loading the courses
        if (isFirstOpen.value) {
            isFirstOpen.value = false;
            void search();
        }
    }

    /**
     * select the modal options
     */
    function select(): void {}

    /**
     * close the modal
     */
    function close(): void {
        isOpen.value = false;
    }

    return {
        isOpen,
        init,
        open,
        select,
        close,
    };
});
