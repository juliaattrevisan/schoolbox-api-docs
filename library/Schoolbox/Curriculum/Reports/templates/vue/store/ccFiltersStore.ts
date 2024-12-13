/**
 * Store for the curriculum coverage filters,
 * so we can refine down what's shown in the report
 */
import locale from "@js/terminology/rosettaStone";
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
            label: `${locale.unit.proper()}`,
        },
        {
            value: ReportOptionValues.LEARNING_ACTIVITY,
            label: `Learning ${locale.activity.properPlural()}`,
        },
        {
            value: ReportOptionValues.LEARNING_ACTIVITY_LESSON_PLAN,
            label: `${locale.lessonPlan.properPlural()}`,
        },
        {
            value: ReportOptionValues.RUBRIC,
            label: `${locale.rubricGroup.properPlural()} & ${locale.rubricCriterion.properPlural()}`,
        },
        {
            value: ReportOptionValues.IN_USE_RUBRIC,
            label: "Rubrics",
        },
    ]); // all report options available
    const selectedReportOptions = window.Vue.ref<ReportOptionValues[]>([
        ...allReportOptions.value.map((option) => option.value),
    ]); // the currently selected report options, just the value list
    const onlyShowGaps = window.Vue.ref<boolean>(false); // whether to only show nodes that contain 0 usage counts

    // to pass through to the csv export, to know which usages to include
    const queryParams = window.Vue.computed(() => {
        return allReportOptions.value.map((option) => {
            return {
                name: option.value,
                value: selectedReportOptions.value.includes(option.value).toString(),
            };
        });
    });

    /**
     * Reset the filters to their default state
     */
    function reset(): void {
        selectedReportOptions.value = [...allReportOptions.value.map((option) => option.value)];
        onlyShowGaps.value = false;
    }

    return {
        allReportOptions,
        selectedReportOptions,
        onlyShowGaps,
        queryParams,
        reset,
    };
});
