<script setup lang="ts">
/**
 * To filter the search results to a date range
 */

import { useFilterDateRangeStore, EMPTY_DATE } from "./store/spFilterDateRangeStore";

const filterDateRangeStore = useFilterDateRangeStore();

/**
 * Clear the stard date if the filters have been reset
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
 * Formats the date picker date to ISO8601 format
 * @param {string} date - The date to format
 * @returns {string} - The formatted date
 */
function getDateFormatted(date: string) {
    return window.dayjs(date, "DD/MM/YYYY").format();
}

/**
 * [data-datepicker-vue] is used to avoid using the attach within
 * www/static/js/alaress-datetime-picker/attacher.datepicker.js as it may be
 * called when the component is not mounted yet.
 */
function initDatePickers() {
    $("[data-datepicker-vue]").each(function attachAlaressDatePicker() {
        const $dateinput = $(this);
        if ($dateinput.attr("data-datepicker-id") === undefined) {
            $dateinput.alaressDateTimePicker();
        }
    });
    $("#sp-start-date-input").on("change", (event) => {
        filterDateRangeStore.startDate = getDateFormatted((event.currentTarget as HTMLInputElement).value);
    });
    $("#sp-end-date-input").on("change", (event) => {
        filterDateRangeStore.endDate = getDateFormatted((event.currentTarget as HTMLInputElement).value);
    });
}

window.Vue.onMounted(() => {
    initDatePickers();
});
</script>

<template>
    <div class="l-flex-col">
        <label>Date range</label>
        <div class="l-flex-row l-gap-s">
            <div class="input-group">
                <input
                    id="sp-start-date-input"
                    type="text"
                    data-datepicker-vue
                    data-datepicker-buttontext
                    data-datesync-enddate="#sp-end-date-input"
                    :data-datepicker-initial-value="filterDateRangeStore.startDate"
                />
            </div>
            <div class="input-group">
                <input
                    id="sp-end-date-input"
                    type="text"
                    data-datepicker-vue
                    data-datepicker-buttontext
                    data-datesync-startdate="#sp-start-date-input"
                    :data-datepicker-initial-value="filterDateRangeStore.endDate"
                />
            </div>
        </div>
    </div>
</template>

<style scoped></style>
