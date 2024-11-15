/**
 * Manage the loading and state of the single cc-usage-pop-out
 */

import { defineStore } from "pinia";
import type { Course } from "@schoolboxLibrary/types/Course";
import type { Unit } from "@schoolboxLibrary/types/Unit";
import { isAbortError } from "@js/util/errorUtils";
import type { CurriculumCoverageNode } from "../../js/types/curriculumCoverageTypes";

/**
 * What is returned when there is a search error
 */
export type ReportError = {
    title: string;
    type: string;
    status: number;
};

export const usePopOutStore = defineStore(`ccPopOut`, () => {
    // #region search for a usage

    const isFetching = window.Vue.ref(false); // to notify that a fetch is taking place
    const isLoading = window.Vue.ref(false);
    const hasLoadError = window.Vue.ref(false); // To show that an error has occurred
    const loadError = window.Vue.ref<ReportError | null>(null);
    let controller: AbortController; // to allow aborting the previous search if a new one is started

    /**
     * search for the requested node usage
     */
    async function search(): Promise<void> {
        // useInputStore().setActive();
        // useQueryStore().setQueryParams();
        // hasNotReported.value = false;
        // abort the previous search if it's still running
        if (isLoading.value) {
            controller.abort();
        }
        try {
            isFetching.value = true;
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
            isFetching.value = false;
            if (!response.ok) {
                hasLoadError.value = true;
                loadError.value = {
                    title: "An error occurred",
                    type: "error",
                    status: response.status,
                };
                return;
            }
            // const responseJson = (await response.json()) as CurriculumCoveragePayload | ReportError;
            // if (typeof responseJson !== "object" || responseJson === null) {
            //     hasLoadError.value = true;
            //     return;
            // }
            // if ("status" in responseJson) {
            //     hasLoadError.value = true;
            //     loadError.value = responseJson;
            //     return;
            // }
            // if ("data" in responseJson) {
            //     courses.value = responseJson.data.courses;
            //     nodes.value = responseJson.data.nodes;
            //     hasReport.value = true;
            // }
            isLoading.value = false;
        } catch (error) {
            // if the error is a DOMException, it's because the report has been aborted to start a new one
            if (typeof error === "string" && !error.includes("DOMException")) {
                hasLoadError.value = true;
            }
        } finally {
            isLoading.value = false;
        }
    }

    // #endregion

    // #region set active to trigger the pop-out

    const course = window.Vue.ref<Course | null>(null);
    const unit = window.Vue.ref<Unit | null>(null);
    const node = window.Vue.ref<CurriculumCoverageNode | null>(null);
    const button = window.Vue.ref<HTMLButtonElement | null>(null);

    /**
     * report has been triggered so set the active course and nodes
     * @param $course - the course to set as active
     * @param $unit - the unit to set as active
     * @param $node - the node to set as active
     * @param $button - the button that triggered the action
     */
    function setActive($course: Course, $unit: Unit, $node: CurriculumCoverageNode, $button: HTMLButtonElement): void {
        course.value = $course;
        unit.value = $unit;
        node.value = $node;
        button.value = $button;
        void search();
    }

    // #endregion

    return {
        button,
        setActive,
    };
});
