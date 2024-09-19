/**
 *
 */

import { defineStore } from "pinia";
import { ReportOptions } from "../tempTypes";

export const useStore = defineStore("curriculumCoverage", () => {
    const allReportOptions = window.Vue.ref<ReportOptions[]>([]); // all report options available
    const selectedReportOptions = window.Vue.ref<string[]>([]); // the currently selected report options, just the value list

    /**
     * store any passed through properties
     */
    function init(): void {}

    return {
        init,
    };
});
