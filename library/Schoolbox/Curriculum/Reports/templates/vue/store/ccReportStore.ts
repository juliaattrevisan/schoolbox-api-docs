/**
 * To generate a report of the curriculum coverage
 */

import { defineStore } from "pinia";
import type { Course } from "@schoolboxLibrary/types/Course";
import type { CurriculumCoveragePayload, CurriculumCoverageNode } from "../../js/types/curriculumCoverageTypes";
import { useQueryStore } from "./ccQueryStore";
import { useInputStore } from "./ccInputStore";

/**
 * What is returned when there is a search error
 */
export type ReportError = {
    title: string;
    type: string;
    status: number;
};

export const useReportStore = defineStore("ccReport", () => {
    const hasNotReported = window.Vue.ref(true); // to prompt a search if the user hasn't searched yet
    const isLoading = window.Vue.ref(false);
    const hasLoadError = window.Vue.ref(false);
    const loadError = window.Vue.ref<ReportError | null>(null);
    const courses = window.Vue.ref<Course[]>([]); // the courses to display in the report
    const hasMultipleCourses = window.Vue.computed(() => courses.value.length > 1); // don't need to show a total for at the end of the unit if there's only one course
    const nodes = window.Vue.ref<CurriculumCoverageNode[]>([]); // the nodes to display in the report
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
     * The total number of columns to display in the report
     * @returns - the total number of columns
     */
    const totalColumnCount = window.Vue.computed(() => {
        let total = 2; // start with 2 for the node name and total
        if (hasMultipleCourses.value) {
            // when there are multiple courses, we need to add the total column
            total += courses.value.reduce((acc, course) => {
                // add 1 for the course name and 1 for the total, then the number of units
                return acc + 1 + 1 + (course.units === undefined ? 0 : course.units.length);
            }, 0);
        } else {
            // add 1 for the course name, then the number of units
            total += 1 + (courses.value[0].units === undefined ? 0 : courses.value[0].units.length);
        }
        return total;
    });

    /**
     * search for nodes that match
     */
    async function report(): Promise<void> {
        useInputStore().setActive();
        useQueryStore().setQueryParams();
        hasNotReported.value = false;
        // abort the previous search if it's still running
        if (isLoading.value) {
            controller.abort();
        }
        try {
            isLoading.value = true;
            hasLoadError.value = false;
            controller = new AbortController();
            const response = await fetch(useQueryStore().getEndpointWithParams(), {
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

    return {
        hasNotReported,
        isLoading,
        hasLoadError,
        report,
        courses,
        hasMultipleCourses,
        nodes,
        hasReport,
        canReport,
        totalColumnCount,
    };
});
