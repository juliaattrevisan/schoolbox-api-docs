/**
 * To manage the state of the curriculum coverage report, handling the load of
 */

import { defineStore } from "pinia";
import type { Course } from "@schoolboxLibrary/types/Course";
import { ReportOptionValues, type ReportOption } from "../../js/types/curriculumCoverageTypes";

export const useStore = defineStore("curriculumCoverage", () => {
    const courses = window.Vue.ref<Course[]>([]); // the courses to display in the report
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
     * store any passed through properties
     */
    function init(): void {}

    return {
        courses,
        allReportOptionValues,
        selectedReportOptionValues,
        onlyShowGaps,
        init,
    };
});
