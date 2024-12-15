/**
 * Store for the curriculum coverage filters,
 * so we can refine down what's shown in the report
 */
import locale from "@js/terminology/rosettaStone";
import { defineStore } from "pinia";
import { ReportOptionValues, type ReportOption } from "../../js/types/curriculumCoverageTypes";

export const useFiltersStore = defineStore("ccFilters", () => {
    // all report options available
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
    ]);

    // the currently selected report options, just the value list, used by cc-report-filters
    const selectedReportOptions = window.Vue.ref<ReportOptionValues[]>([
        ...allReportOptions.value.map((option) => option.value),
    ]);

    // loop through the selected report options and join them together to create a key
    const selectedReportOptionsKey = window.Vue.computed(() => {
        let key = "";
        allReportOptions.value.forEach((option) => {
            if (selectedReportOptions.value.includes(option.value)) {
                key += `${option.value}-`;
            }
        });
        return key;
    });

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
        selectedReportOptionsKey,
        onlyShowGaps,
        queryParams,
        reset,
    };
});
