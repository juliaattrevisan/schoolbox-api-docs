/**
 * To manage the state of the curriculum coverage report, handling the load of
 */

import { defineStore } from "pinia";
import type { Course } from "@schoolboxLibrary/types/Course";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { ReportOptionValues, type ReportOption } from "../../js/types/curriculumCoverageTypes";

export const useStore = defineStore("curriculumCoverage", () => {
    const selectedCourses = window.Vue.ref<Course[]>([]); // the selected courses @todo - this will be stored in a selectedCoursesStore instead with SA-18582
    const courses = window.Vue.ref<Course[]>([]); // the courses to display in the report
    const nodes = window.Vue.ref<Node[]>([]); // the nodes to display in the report
    const hasReport = window.Vue.ref<boolean>(false); // whether the report has been generated
    const isLoading = window.Vue.ref<boolean>(false); // whether the report is currently loading
    const allReportOptionValues = window.Vue.ref<ReportOption[]>([
        {
            value: ReportOptionValues.COURSE,
            label: "Course",
        },
        {
            value: ReportOptionValues.UNIT,
            label: "Unit",
        },
        {
            value: ReportOptionValues.LEARNING_ACTIVITY,
            label: "Learning Activity",
        },
        {
            value: ReportOptionValues.LEARNING_ACTIVITY_LESSON_PLAN,
            label: "Lesson Plans",
        },
        {
            value: ReportOptionValues.RUBRIC,
            label: "Rubric",
        },
    ]); // all report options available
    const selectedReportOptionValues = window.Vue.ref<ReportOptionValues[]>([
        ...allReportOptionValues.value.map((option) => option.value),
    ]); // the currently selected report options, just the value list
    const onlyShowGaps = window.Vue.ref<boolean>(false); // whether to only show nodes that contain 0 usage counts

    /**
     * retrieve the report
     */
    async function search(): Promise<void> {
        // useQueryStore().setActive();
        // useFiltersStore().setActive();
        // useResultsStore().clear();
        // useStore().setQueryParams(simulateRedirect);
        // hasNotSearched.value = false;
        // url.value = useStore().getEndpointWithParams();
        // abort the previous search if it's still running
        // if (isLoading.value) {
        //     controller.abort();
        // }
        try {
            isLoading.value = true;
            // hasLoadError.value = false;
            // controller = new AbortController();
            const response = await fetch(
                `https://vagrant.dev.schoolbox.cloud/curriculum/reports/coverage?courseId[]=5&courseId[]=13&nodeId=1997,3105`,
                {
                    // signal: controller.signal,
                    headers: {
                        Accept: "application/json",
                    },
                },
            );
            const responseJson = await response.json();
            courses.value = responseJson.data.courses;
            nodes.value = responseJson.data.nodes;
            hasReport.value = true;
            console.warn(responseJson);
            // if there's a status, it's an error
            // if ((responseJson as SearchError).status) {
            //     hasLoadError.value = true;
            //     searchError.value = responseJson as SearchError;
            // } else {
            //     useResultsStore().setFirstResults(responseJson as Results);
            // }
            isLoading.value = false;
        } catch (error) {
            console.warn(error);
            // if the error is a DOMException, it's because the search has been aborted to start a new one
            // if (typeof error === "string" && !error.includes("DOMException")) {
            //     hasLoadError.value = true;
            // }
        }
    }

    /**
     * store any passed through properties
     * @param $courses - the courses to display in the report
     */
    function init($courses: Course[]): void {
        selectedCourses.value = $courses;
        void search();
    }

    return {
        selectedCourses,
        courses,
        nodes,
        hasReport,
        isLoading,
        allReportOptionValues,
        selectedReportOptionValues,
        onlyShowGaps,
        init,
    };
});
