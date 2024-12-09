/**
 * Load more of the selected curriculum if available
 */

import { defineStore } from "pinia";
import { isAbortError } from "@js/util/errorUtils";
import type { CurriculumCoveragePayload, ReportError } from "../../js/types/curriculumCoverageTypes";
import { useQueryStore } from "./ccQueryStore";
import { useCoursesStore } from "./ccCoursesStore";
import { useNodesStore } from "./ccNodesStore";

export const useLoadMoreStore = defineStore("ccLoadMore", () => {
    const next = window.Vue.ref<string | null>(null);
    const hasMore = window.Vue.computed(() => next.value !== null);
    const isFetching = window.Vue.ref(false);
    const isLoading = window.Vue.ref(false);
    const hasLoadError = window.Vue.ref(false);
    const loadError = window.Vue.ref<ReportError | null>(null);
    let controller: AbortController; // to allow aborting the previous search if a new one is started

    /**
     * Set the next cursor to load
     * @param $next - the next url to load
     */
    function setNext($next: string | null): void {
        next.value = $next;
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
                hasLoadError.value = true;
                loadError.value = {
                    title: "An error occurred",
                    type: "error",
                    status: response.status,
                };
                return;
            }
            const responseJson = (await response.json()) as CurriculumCoveragePayload | ReportError;
            if (typeof responseJson !== "object" || responseJson === null) {
                hasLoadError.value = true;
                return;
            }
            if ("status" in responseJson) {
                hasLoadError.value = true;
                loadError.value = responseJson;
                return;
            }
            if ("data" in responseJson && "metadata" in responseJson) {
                const nodesStore = useNodesStore();
                // if it's the first course load, we can just set the courses and nodes
                if (currentIndex === 0) {
                    nodesStore.setMoreNodes(responseJson.data.nodes);
                    setNext(responseJson.metadata.cursor.next);
                } else {
                    // and we also need to run through nodes and add the new course within each node.usage.courses[]
                    // then if the node has children, we need to do the same for the children within
                    nodesStore.addMoreNodes(responseJson.data.nodes);
                }
            }
        } catch (error) {
            // ignore abort errors, as the previous report was cancelled
            if (isAbortError(error)) {
                return;
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
            const queryUrls = useQueryStore()
                .getQueryUrls()
                .map((url) => `${url}&cursor=${next.value}`);
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
        hasMore,
        isLoading,
        hasLoadError,
        report,
    };
});
