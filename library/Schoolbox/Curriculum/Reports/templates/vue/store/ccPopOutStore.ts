/**
 * Manage the loading and state of the single usage display, used to display the usage of a single node
 */

import { defineStore } from "pinia";
import type { Course } from "@schoolboxLibrary/types/Course";
import type { Unit } from "@schoolboxLibrary/types/Unit";
import { isAbortError } from "@js/util/errorUtils";
import type { CurriculumCoverageNode, IndividualUsage } from "../../js/types/curriculumCoverageTypes";

/**
 * What is returned when there is a search error
 */
export type ReportError = {
    title: string;
    type: string;
    status: number;
};

/**
 * The payload for the individual usage
 */
type IndividualUsagePayload = {
    data: IndividualUsage;
};

export const usePopOutStore = defineStore(`ccPopOut`, () => {
    const course = window.Vue.ref<Course | null>(null);
    const unit = window.Vue.ref<Unit | null>(null);
    const node = window.Vue.ref<CurriculumCoverageNode | null>(null);
    const button = window.Vue.ref<HTMLButtonElement | null>(null);
    const isNodeExpanded = window.Vue.ref<boolean>(false);

    // #region search for a usage

    let endpoint = ""; // the endpoint to use for the usage
    const isFetching = window.Vue.ref(false); // to notify that a fetch is taking place
    const isLoading = window.Vue.ref(false);
    const hasLoadError = window.Vue.ref(false); // To show that an error has occurred
    const loadError = window.Vue.ref<ReportError | null>(null);
    const usage = window.Vue.ref<IndividualUsage | null>(null); // the usage to display in the pop-out
    let controller: AbortController; // to allow aborting the previous search if a new one is started

    /**
     * store any passed through properties
     * @param $endpoint - the report api url
     */
    function init($endpoint: string): void {
        endpoint = $endpoint;
    }

    /**
     * To collect all the params to send to the search api
     * @returns - the query params
     */
    function getQueryParams(): URLSearchParams {
        const newQueryParams = new URLSearchParams();
        newQueryParams.append("courseId", course.value?.id.toString() ?? "");
        newQueryParams.append("unitId", unit.value?.id.toString() ?? "");
        newQueryParams.append("nodeId", node.value?.id.toString() ?? "");
        return newQueryParams;
    }

    /**
     * To collect all the params to send to the search api
     * @returns - the query params
     */
    function getEndpointWithParams(): string {
        const newQueryParams = getQueryParams();
        return `${endpoint}?${newQueryParams.toString()}`;
    }

    /**
     * search for the requested node usage
     */
    async function search(): Promise<void> {
        // abort the previous search if it's still running
        if (isLoading.value) {
            controller.abort();
        }
        try {
            isFetching.value = true;
            isLoading.value = true;
            hasLoadError.value = false;
            controller = new AbortController();
            const response = await fetch(getEndpointWithParams(), {
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
            const responseJson = (await response.json()) as IndividualUsagePayload | ReportError;
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
                usage.value = responseJson.data;
            }
            isLoading.value = false;
        } catch (error) {
            // ignore abort errors, as the previous search was cancelled
            if (isAbortError(error)) {
                return;
            }
            // if the error is a DOMException, it's because the report has been aborted to start a new one
            if (typeof error === "string" && !error.includes("DOMException")) {
                hasLoadError.value = true;
            }
        } finally {
            isLoading.value = false;
        }
    }

    // #endregion

    /**
     * report has been triggered so set the active course and nodes
     * @param $course - the course to set as active
     * @param $unit - the unit to set as active
     * @param $node - the node to set as active
     * @param $button - the button that triggered the action
     * @param $isNodeExpanded - whether the node is expanded
     */
    function setActive(
        $course: Course,
        $unit: Unit,
        $node: CurriculumCoverageNode,
        $button: HTMLButtonElement,
        $isNodeExpanded: boolean,
    ): void {
        course.value = $course;
        unit.value = $unit;
        node.value = $node;
        button.value = $button;
        isNodeExpanded.value = $isNodeExpanded;
        void search();
    }

    return {
        button,
        isNodeExpanded,
        isLoading,
        hasLoadError,
        init,
        setActive,
    };
});
