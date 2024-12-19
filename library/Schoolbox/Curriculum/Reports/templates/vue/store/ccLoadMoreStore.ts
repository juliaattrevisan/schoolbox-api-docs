/**
 * Load more of the selected curriculum if available
 */

import { defineStore } from "pinia";
import { isAbortError, hasErrorMessage } from "@js/util/errorUtils";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import type { CurriculumCoveragePayload } from "../../js/types/curriculumCoverageTypes";
import { useInputStore } from "./ccInputStore";
import { useQueryStore } from "./ccQueryStore";
import { useNodesStore } from "./ccNodesStore";

export const useLoadMoreStore = defineStore("ccLoadMore", () => {
    const nextList = window.Vue.ref<string[]>([]);
    const hasMore = window.Vue.computed(
        () => useNodesStore().nodes.length < useInputStore().activeNodeCount && nextList.value.length > 0,
    ); // don't load more if all nodes have been loaded or the next list is empty
    const isFetching = window.Vue.ref(false);
    const isLoading = window.Vue.ref(false);
    const hasLoadError = window.Vue.ref(false);
    const loadError = window.Vue.ref<string | null>(null);
    let controller: AbortController; // to allow aborting the previous search if a new one is started

    /**
     * Set the a new cursor list to load
     * @param $next - the next url to load
     */
    function setNext($next: string | null): void {
        if ($next !== null) {
            nextList.value = [$next];
        } else {
            nextList.value = [];
        }
    }

    /**
     * Set the a new cursor list to load
     * @param $next - the next url to load
     */
    function addNext($next: string | null): void {
        if ($next !== null) {
            nextList.value = [...nextList.value, $next];
        } else {
            nextList.value = [];
        }
    }

    /**
     * Load the course/node data for a single course
     * @param url - the url to load, linked to the single course and all curriculum
     * @param currentIndex - the current load index
     */
    async function getCourse(url: string, currentIndex: number): Promise<void> {
        try {
            const response = await fetch(url, {
                method: "GET",
                signal: controller.signal,
                headers: {
                    Accept: "application/json",
                },
            });
            if (!response.ok) {
                const errorMessage = await extractErrorMessage(response);
                throw new Error(errorMessage);
            }
            const responseJson = (await response.json()) as CurriculumCoveragePayload;
            if (typeof responseJson !== "object" || responseJson === null) {
                throw new Error("Unexpected response format");
            }
            if ("data" in responseJson && "metadata" in responseJson) {
                const nodesStore = useNodesStore();
                // if it's the first course load, we can just set the courses, nodes, and next cursor
                if (currentIndex === 0) {
                    nodesStore.setMoreNodes(responseJson.data.nodes);
                    setNext(responseJson.metadata.cursor.next);
                } else {
                    // otherwise we need to add the new to the existing
                    nodesStore.addMoreCourseUsage(responseJson.data.nodes);
                    addNext(responseJson.metadata.cursor.next);
                }
                if (currentIndex === useQueryStore().getQueryUrls().length - 1) {
                    nodesStore.setDefaults();
                }
            } else {
                throw new Error("Unexpected response format");
            }
        } catch (error) {
            // ignore abort errors, as the previous report was cancelled
            if (isAbortError(error)) {
                return;
            }
            if (error instanceof Error && hasErrorMessage(error)) {
                loadError.value = error.message;
            }
            console.error(error);
            hasLoadError.value = true;
        }
    }

    /**
     * In an attempt to reduce server load,
     * the report is generated sequentially for each course
     */
    async function report(): Promise<void> {
        isLoading.value = true;
        hasLoadError.value = false;
        controller = new AbortController();
        // abort the previous search if it's still running
        if (isFetching.value) {
            controller.abort();
        }
        try {
            isFetching.value = true;
            // get the next set of nodes to load, setting the list of next cursors
            const queryUrls = useQueryStore()
                .getQueryUrls()
                .map((url, index) => `${url}&cursor=${nextList.value[index]}`);
            await queryUrls.reduce(async (previousPromise, url, currentIndex) => {
                await previousPromise;
                if (!hasLoadError.value) {
                    await getCourse(url, currentIndex);
                }
            }, Promise.resolve());
        } catch (error) {
            console.error(error);
            hasLoadError.value = true;
        } finally {
            isFetching.value = false;
            isLoading.value = false;
        }
    }

    return {
        setNext,
        addNext,
        hasMore,
        isLoading,
        hasLoadError,
        loadError,
        report,
    };
});
