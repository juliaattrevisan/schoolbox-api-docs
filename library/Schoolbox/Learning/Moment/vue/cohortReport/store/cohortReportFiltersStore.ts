import { defineStore } from "pinia";
import { keysOf } from "@js/util/arrayUtils";
import type { SelectizeSelectorItem } from "@js/vendors/selectize/selectizeSelectorTypes";
import { EMPTY_DATE } from "@schoolboxVue/components/dateTimePicker/datePickerTypes";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import { evidenceStatusLabels } from "@schoolboxLearningMomentEvidence/evidenceTypes";
import type { EvidenceStatus } from "@schoolboxLearningMomentEvidence/evidenceTypes";

export const useCohortReportFiltersStore = defineStore("cohortReportFilters", () => {
    const studentFilter = window.Vue.ref<SearchSelectItem[]>([]);
    const authorFilter = window.Vue.ref<SearchSelectItem[]>([]);
    const stateFilter = window.Vue.ref<EvidenceStatus[]>(keysOf(evidenceStatusLabels));
    const evidenceTagsFilter = window.Vue.ref<SelectizeSelectorItem["id"][]>([]);
    const startDateFilter = window.Vue.ref<string>(EMPTY_DATE);
    const endDateFilter = window.Vue.ref<string>(EMPTY_DATE);

    /**
     * Reset all filters to their default values
     */
    function resetFilters(): void {
        studentFilter.value = [];
        authorFilter.value = [];
        stateFilter.value = keysOf(evidenceStatusLabels);
        evidenceTagsFilter.value = [];
        startDateFilter.value = EMPTY_DATE;
        endDateFilter.value = EMPTY_DATE;
    }

    return {
        resetFilters,
        studentFilter,
        authorFilter,
        stateFilter,
        evidenceTagsFilter,
        startDateFilter,
        endDateFilter,
    };
});
