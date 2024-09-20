/**
 * To manage the state of the curriculum coverage report, handling the load of
 */

import { defineStore } from "pinia";
import { ReportOptionValues, type ReportOption } from "../../js/types/curriculumCoverageTypes";

export const useStore = defineStore("curriculumCoverage", () => {
    const allReportOptionValues = window.Vue.ref<ReportOption[]>([
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

    /**
     * store any passed through properties
     */
    function init(): void {}

    return {
        init,
        allReportOptionValues,
        selectedReportOptionValues,
    };
});
