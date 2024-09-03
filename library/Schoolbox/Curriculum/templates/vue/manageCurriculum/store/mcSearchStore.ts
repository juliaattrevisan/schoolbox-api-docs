/**
 * Store the current search results when searcing from mc-view-header-search-input
 */

/* eslint-disable import/no-cycle -- Disable dependency cycle warning temporarily [SA-18428] */

import { defineStore } from "pinia";
import { useStore } from "@schoolboxCurriculum/vue/manageCurriculum/store/manageCurriculumStore";
import { useViewNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/viewNodes/store/mcViewNodesStore";
import type { Node } from "@schoolboxCurriculum/types/Node";

const MIN_QUERY_LENGTH = 2; // the amount of characters typed before the search becomes active
export const DEBOUNCE_AMOUNT = 350; // the amount in ms to hold off on searching, to allow the user to keep typing

export const useSearchStore = defineStore(`mcSearch`, () => {
    const isLoading = window.Vue.ref(false); // to notify that an action is taking place,
    const query = window.Vue.ref(""); // current search term to filter the active nodes
    const results = window.Vue.ref<Node[]>([]); // nodes returned in the current search results

    /**
     * To only start showing search results once enough characters
     * have been added
     * @returns { boolean }
     */
    const isActive = window.Vue.computed(() => query.value.length >= MIN_QUERY_LENGTH);

    /**
     * add any filters, should we be showing existing results?
     * @returns { Node[] }
     */
    const filteredResults = window.Vue.computed(() => (isActive.value ? (results.value as Node[]) : []));

    /**
     * To show that no nodes match the current search
     * @returns { boolean }
     */
    const hasNoResults = window.Vue.computed(() => {
        return isActive.value && filteredResults.value.length === 0 && !isLoading.value;
    });

    /**
     * when the search isActive, we should display the loading prompt straight away,
     * but the debounce holds on calling search() until we assume the user has stopped typing
     */
    function enableSearch() {
        isLoading.value = true;
    }

    /**
     * search for nodes that match
     */
    async function search() {
        try {
            const viewNodesStore = useViewNodesStore();
            const enabled = viewNodesStore.showDisabled ? "" : "&enabled=1";
            const response = await fetch(
                `${useStore().getApiEndpoint()}?parentId=${viewNodesStore.current.id}&query=${
                    query.value
                }&limit=50${enabled}`,
                {
                    method: "GET",
                    headers: {
                        "Content-Type": "application/json",
                    },
                },
            );
            results.value = (await response.json()) as Node[];
        } catch (error) {
            console.error(error);
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * clear the current search
     */
    function clear() {
        query.value = "";
        results.value = [];
        isLoading.value = false;
    }

    return {
        isLoading,
        query,
        results,
        isActive,
        filteredResults,
        hasNoResults,
        enableSearch,
        search,
        clear,
    };
});
