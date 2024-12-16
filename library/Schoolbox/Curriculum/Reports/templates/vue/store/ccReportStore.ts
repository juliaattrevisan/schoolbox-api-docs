/**
 * To generate a report of the curriculum coverage
 */

import { defineStore } from "pinia";
import { isAbortError, hasErrorMessage } from "@js/util/errorUtils";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import type { CurriculumCoveragePayload } from "../../js/types/curriculumCoverageTypes";
import { useQueryStore } from "./ccQueryStore";
import { useInputStore } from "./ccInputStore";
import { useFiltersStore } from "./ccFiltersStore";
import { useCoursesStore } from "./ccCoursesStore";
import { useNodesStore } from "./ccNodesStore";
import { useLoadMoreStore } from "./ccLoadMoreStore";

export const useReportStore = defineStore("ccReport", () => {
    const hasNotReported = window.Vue.ref(true); // to prompt a search if the user hasn't searched yet
    const isFetching = window.Vue.ref(false);
    const isLoading = window.Vue.ref(false);
    const hasLoadError = window.Vue.ref(false);
    const loadError = window.Vue.ref<string | null>(null);
    const hasReport = window.Vue.ref<boolean>(false); // whether the report has been generated
    let controller: AbortController; // to allow aborting the previous search if a new one is started

    /**
     * Whether a search can be performed, can be if there's a change of course or node selection
     * @returns - whether a new report can be requested
     */
    const canReport = window.Vue.computed(() => {
        return useInputStore().canReport;
    });

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
                const coursesStore = useCoursesStore();
                const nodesStore = useNodesStore();
                const loadMoreStore = useLoadMoreStore();
                // if it's the first course load, we can just set the courses, nodes, and next cursor
                if (currentIndex === 0) {
                    coursesStore.setCourses(responseJson.data.courses);
                    nodesStore.setNodes(responseJson.data.nodes);
                    loadMoreStore.setNext(responseJson.metadata.cursor.next);
                } else {
                    // otherwise we need to add the new to the existing
                    coursesStore.addCourses(responseJson.data.courses);
                    const newCourseNodes = responseJson.data.nodes;
                    nodesStore.addCourseUsage(newCourseNodes);
                    loadMoreStore.addNext(responseJson.metadata.cursor.next);
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
        useInputStore().setActive();
        useQueryStore().setQueryParams();
        useFiltersStore().reset();
        hasNotReported.value = false;
        isLoading.value = true;
        hasLoadError.value = false;
        controller = new AbortController();
        // abort the previous search if it's still running
        if (isFetching.value) {
            controller.abort();
        }
        try {
            isFetching.value = true;
            await useQueryStore()
                .getQueryUrls()
                .reduce(async (previousPromise, url, currentIndex) => {
                    await previousPromise;
                    if (!hasLoadError.value) {
                        await getCourse(url, currentIndex);
                    }
                }, Promise.resolve());
            hasReport.value = true;
        } catch (error) {
            console.error(error);
            hasLoadError.value = true;
        } finally {
            isFetching.value = false;
            isLoading.value = false;
        }
    }

    return {
        hasNotReported,
        isLoading,
        hasLoadError,
        loadError,
        report,
        hasReport,
        canReport,
    };
});
