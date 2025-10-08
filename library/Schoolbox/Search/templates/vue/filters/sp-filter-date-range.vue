<script setup lang="ts">
/**
 * To filter the search results to a date range
 */

import DateRange from "@schoolboxVue/components/dateTimePicker/date-range.vue";
import { useFilterDateRangeStore } from "./store/spFilterDateRangeStore";
import { useSearchStore } from "../store/spSearchStore";

const filterDateRangeStore = useFilterDateRangeStore();
const searchStore = useSearchStore();

const dateRangeEl = window.Vue.useTemplateRef<InstanceType<typeof DateRange>>("dateRangeRef");

/**
 * Clear the invalid date input if we are searching, as if it's invalid we don't search
 * with the date ranges set, so the UI should reflect that.
 */
window.Vue.watch(
    () => searchStore.isSearching,
    (isSearching) => {
        if (isSearching && dateRangeEl.value) {
            dateRangeEl.value.clearInvalidDates();
        }
    },
);
</script>

<template>
    <date-range
        ref="dateRangeRef"
        v-model:start-date="filterDateRangeStore.startDate"
        v-model:end-date="filterDateRangeStore.endDate"
        id-prefix="sp"
        data-test-prefix="sp-filter"
    />
</template>

<style scoped></style>
