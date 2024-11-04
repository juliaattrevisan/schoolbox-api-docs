/**
 * Store for the curriculum coverage filters,
 * so we can refine down what's shown in the report
 */

import { defineStore } from "pinia";
import { ReportOptionValues, type ReportOption } from "../../js/types/curriculumCoverageTypes";

export const useFiltersStore = defineStore("ccFilters", () => {
    const allReportOptions = window.Vue.ref<ReportOption[]>([
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
        {
            value: ReportOptionValues.IN_USE_RUBRIC,
            label: "In-use Rubric",
        },
    ]); // all report options available
    const selectedReportOptions = window.Vue.ref<ReportOptionValues[]>([
        ...allReportOptions.value.map((option) => option.value),
    ]); // the currently selected report options, just the value list
    const onlyShowGaps = window.Vue.ref<boolean>(false); // whether to only show nodes that contain 0 usage counts

    return {
        allReportOptions,
        selectedReportOptions,
        onlyShowGaps,
    };
});
