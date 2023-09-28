<script setup lang="ts">
/**
 * To filter the search results to a date range
 */

import { useFilterDateRangeStore, EMPTY_DATE } from "./store/spFilterDateRangeStore";

const filterDateRangeStore = useFilterDateRangeStore();

/**
 * Clear the start date if the filters have been reset
 */
window.Vue.watch(
    () => filterDateRangeStore.startDate,
    (newValue) => {
        if (newValue === EMPTY_DATE) {
            $("#sp-start-date-input").val(EMPTY_DATE);
        }
    }
);

/**
 * Clear the end date if the filters have been reset
 */
window.Vue.watch(
    () => filterDateRangeStore.endDate,
    (newValue) => {
        if (newValue === EMPTY_DATE) {
            $("#sp-end-date-input").val(EMPTY_DATE);
        }
    }
);

/**
 * [data-datepicker-vue] is used to avoid using the attach within
 * www/static/js/alaress-datetime-picker/attacher.datepicker.js as it may be
 * called when the component is not mounted yet.
 */
function initDatePickers() {
    $("[data-datepicker-vue]").each(function attachAlaressDatePicker() {
        const $dateInput = $(this);
        if ($dateInput.attr("data-datepicker-id") === undefined) {
            $dateInput.alaressDateTimePicker();
        }
    });
}

window.Vue.onMounted(() => {
    initDatePickers();
});
</script>

<template>
    <div class="l-flex-col">
        <label for="sp-start-date-input">Start date</label>
        <input
            id="sp-start-date-input"
            type="text"
            data-datepicker-vue
            data-datepicker-buttontext
            data-datesync-enddate="#sp-end-date-input"
            :value="filterDateRangeStore.startDate"
        />
    </div>
    <div class="l-flex-col">
        <label for="sp-end-date-input">End date</label>
        <input
            id="sp-end-date-input"
            type="text"
            data-datepicker-vue
            data-datepicker-buttontext
            data-datesync-startdate="#sp-start-date-input"
            :value="filterDateRangeStore.endDate"
        />
    </div>
</template>

<style scoped></style>
