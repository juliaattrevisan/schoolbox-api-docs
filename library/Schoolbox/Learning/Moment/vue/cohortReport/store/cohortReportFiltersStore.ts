import { defineStore } from "pinia";
import { keysOf } from "@js/util/arrayUtils";
import type { SelectizeSelectorItem } from "@js/vendors/selectize/selectizeSelectorTypes";
import { EMPTY_DATE } from "@schoolboxVue/components/dateTimePicker/datePickerTypes";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import { evidenceStatusLabels } from "@schoolboxLearningMomentEvidence/evidenceTypes";
import type { EvidenceStatus } from "@schoolboxLearningMomentEvidence/evidenceTypes";
import { useCohortReportStore } from "./cohortReportStore";

export const useCohortReportFiltersStore = defineStore("cohortReportFilters", () => {
    const crStore = useCohortReportStore();

    const studentFilter = window.Vue.ref<SearchSelectItem[]>([]);
    const authorFilter = window.Vue.ref<SearchSelectItem[]>([]);
    const folderFilter = window.Vue.ref<SearchSelectItem[]>([]);
    const stateFilter = window.Vue.ref<EvidenceStatus[]>(keysOf(evidenceStatusLabels));
    const evidenceTagsFilter = window.Vue.ref<SelectizeSelectorItem[]>([]);
    const startDateFilter = window.Vue.ref<string>(EMPTY_DATE);
    const endDateFilter = window.Vue.ref<string>(EMPTY_DATE);

    /**
     * Reset all filters to their default values
     */
    function resetFilters(): void {
        studentFilter.value = [];
        authorFilter.value = [];
        folderFilter.value = [];
        stateFilter.value = keysOf(evidenceStatusLabels);
        evidenceTagsFilter.value = [];
        startDateFilter.value = EMPTY_DATE;
        endDateFilter.value = EMPTY_DATE;
    }

    /**
     * Apply the filters and refresh the cohort report
     */
    async function applyFilters(): Promise<void> {
        // clear existing data in the store as it's now invalid
        crStore.clearData();

        // now apply the new filters and load the first page
        crStore.activeFilters = {
            userIds: Object.values(studentFilter.value).map((s) => Number(s.id)),
            authorIds: Object.values(authorFilter.value).map((a) => Number(a.id)),
            folderIds: Object.values(folderFilter.value).map((f) => Number(f.id)),
            tagIds: evidenceTagsFilter.value.map((t) => Number(t.id)),
            states: [...stateFilter.value],
            from: startDateFilter.value ? window.dayjs(startDateFilter.value).format("YYYY-MM-DD") : undefined,
            to: endDateFilter.value ? window.dayjs(endDateFilter.value).format("YYYY-MM-DD") : undefined,
        };

        await crStore.loadNextPage();
    }

    return {
        resetFilters,
        applyFilters,
        studentFilter,
        authorFilter,
        folderFilter,
        stateFilter,
        evidenceTagsFilter,
        startDateFilter,
        endDateFilter,
    };
});
