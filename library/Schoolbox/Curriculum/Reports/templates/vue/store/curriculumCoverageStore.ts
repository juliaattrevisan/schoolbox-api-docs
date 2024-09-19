/**
 *
 */

import { defineStore } from "pinia";
import { ReportOptions } from "../tempTypes";

export type ReportOption = {
    value: ReportOptions;
    label: string;
};

export const useStore = defineStore("curriculumCoverage", () => {
    const allReportOptions = window.Vue.ref<ReportOption[]>([
        {
            value: ReportOptions.UNIT,
            label: "Unit",
        },
        {
            value: ReportOptions.LEARNING_ACTIVITY,
            label: "Learning Activity",
        },
        {
            value: ReportOptions.LEARNING_ACTIVITY_LESSON_PLAN,
            label: "Lesson Plans",
        },
        {
            value: ReportOptions.RUBRIC,
            label: "Rubric",
        },
    ]); // all report options available
    const selectedReportOptions = window.Vue.ref<string[]>([]); // the currently selected report options, just the value list

    /**
     * store any passed through properties
     */
    function init(): void {}

    return {
        init,
        allReportOptions,
        selectedReportOptions,
    };
});
