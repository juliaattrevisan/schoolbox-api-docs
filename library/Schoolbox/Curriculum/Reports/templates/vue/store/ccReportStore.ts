/**
 * To generate a report of the curriculum coverage
 */

import { defineStore } from "pinia";
import type { Course } from "@schoolboxLibrary/types/Course";
import type { CurriculumCoveragePayload, CurriculumCoverageNode } from "../../js/types/curriculumCoverageTypes";

/**
 * What is returned when there is a search error
 */
export type ReportError = {
    title: string;
    type: string;
    status: number;
};

export const useReportStore = defineStore("cpReport", () => {
    const hasNotSearched = window.Vue.ref(true); // to prompt a search if the user hasn't searched yet
    // const url = window.Vue.ref("");
    const isLoading = window.Vue.ref(false);
    const hasLoadError = window.Vue.ref(false);
    const loadError = window.Vue.ref<ReportError | null>(null);
    const courses = window.Vue.ref<Course[]>([]); // the courses to display in the report
    const nodes = window.Vue.ref<CurriculumCoverageNode[]>([]); // the nodes to display in the report
    const hasReport = window.Vue.ref<boolean>(false); // whether the report has been generated
    let controller: AbortController; // to allow aborting the previous search if a new one is started

    /**
     * Whether a search can be performed, can be if there's a change of query or filters
     * @returns {boolean} - whether a new search can be performed
     */
    // const canSearch = window.Vue.computed(() => {
    //     return useQueryStore().canSearch || useFiltersStore().canSearch;
    // });

    /**
     * search for nodes that match
     */
    async function report(): Promise<void> {
        // useQueryStore().setActive();
        // useFiltersStore().setActive();
        // useResultsStore().clear();
        // useStore().setQueryParams(simulateRedirect);
        hasNotSearched.value = false;
        // url.value = useStore().getEndpointWithParams();
        // abort the previous search if it's still running
        if (isLoading.value) {
            controller.abort();
        }
        try {
            isLoading.value = true;
            hasLoadError.value = false;
            controller = new AbortController();
            const response = await fetch(
                `https://vagrant.dev.schoolbox.cloud/curriculum/reports/coverage?courseId[]=5&courseId[]=13&nodeId=1997,3105`,
                {
                    method: "GET",
                    signal: controller.signal,
                    headers: {
                        Accept: "application/json",
                    },
                },
            );
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
            if ("data" in responseJson) {
                courses.value = responseJson.data.courses;
                nodes.value = responseJson.data.nodes;
                hasReport.value = true;
            }
            isLoading.value = false;
        } catch (error) {
            // if the error is a DOMException, it's because the report has been aborted to start a new one
            if (typeof error === "string" && !error.includes("DOMException")) {
                hasLoadError.value = true;
            }
        }
    }

    /**
     * clear the filters and search
     */
    // async function clearFiltersAndSearch(): Promise<void> {
    //     useFiltersStore().clear();
    //     await search(true);
    // }

    return {
        hasNotSearched,
        isLoading,
        hasLoadError,
        report,
        courses,
        nodes,
        hasReport,
        // canSearch,
    };
});
