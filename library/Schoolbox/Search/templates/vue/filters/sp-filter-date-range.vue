<script setup lang="ts">
/**
 * To filter the search results to a date range
 */

import { useFilterDateRangeStore, EMPTY_DATE, INVALID_DATE } from "./store/spFilterDateRangeStore";
import { useSearchStore } from "../store/spSearchStore";

const filterDateRangeStore = useFilterDateRangeStore();
const searchStore = useSearchStore();

const startDateInputEl = window.Vue.ref<HTMLInputElement | null>(null);
const endDateInputEl = window.Vue.ref<HTMLInputElement | null>(null);

/**
 * Ensure the error message is hidden on clear filters
 * @param {string} selector - The selector for the error message
 */
function clearErrorMessage(selector: string) {
    const dateError = document.querySelector(selector) as HTMLDivElement;
    if (dateError) {
        dateError.style.display = "none";
    }
}

/**
 * Clear the start date if the filters have been reset
 */
window.Vue.watch(
    () => filterDateRangeStore.startDate,
    (newValue) => {
        if (newValue === EMPTY_DATE) {
            if (startDateInputEl.value) {
                startDateInputEl.value.value = EMPTY_DATE;
            }
            clearErrorMessage("#sp-start-date-input-error");
        }
    },
);

/**
 * Clear the end date if the filters have been reset
 */
window.Vue.watch(
    () => filterDateRangeStore.endDate,
    (newValue) => {
        if (newValue === EMPTY_DATE) {
            if (endDateInputEl.value) {
                endDateInputEl.value.value = EMPTY_DATE;
            }
            clearErrorMessage("#sp-end-date-input-error");
        }
    },
);

/**
 * Clear the invalid date input if we are searching, as if it's invalid we don't search
 * with the date ranges set, so the UI should reflect that.
 */
window.Vue.watch(
    () => searchStore.isSearching,
    (isSearching) => {
        if (isSearching && filterDateRangeStore.startDate === INVALID_DATE) {
            filterDateRangeStore.startDate = EMPTY_DATE;
        }
        if (isSearching && filterDateRangeStore.endDate === INVALID_DATE) {
            filterDateRangeStore.endDate = EMPTY_DATE;
        }
    },
);

/**
 * Formats the date picker date to ISO8601 format
 * @param {string} date - The date to format
 * @returns {string} - The formatted date
 */
function getDateFormatted(date: string) {
    return window.dayjs(date, window.dateDisplayFormats.dayjs).format();
}

/**
 * [data-datepicker-vue] is used to avoid using the attach within
 * www/static/js/alaress-datetime-picker/attacher.datepicker.js as it may be
 * called when the component is not mounted yet.
 */
function initDatePickers() {
    // For now, disable eslint as we need to use jQuery to attach the date picker
    /* eslint-disable @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access */
    $("[data-datepicker-vue]").each(function attachAlaressDatePicker() {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        const $dateInput = $(this);
        if ($dateInput.attr("data-datepicker-id") === undefined) {
            $dateInput.alaressDateTimePicker();
        }
    });
    $("#sp-start-date-input").on("change", (event) => {
        const dateFromDom = (event.currentTarget as HTMLInputElement).value;
        filterDateRangeStore.startDate = dateFromDom === EMPTY_DATE ? EMPTY_DATE : getDateFormatted(dateFromDom);
    });
    $("#sp-end-date-input").on("change", (event) => {
        const dateFromDom = (event.currentTarget as HTMLInputElement).value;
        filterDateRangeStore.endDate = dateFromDom === EMPTY_DATE ? EMPTY_DATE : getDateFormatted(dateFromDom);
    });
    /* eslint-enable @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access */
}

window.Vue.onMounted(() => {
    initDatePickers();
});
</script>

<template>
    <div class="l-flex-row l-flex--no-wrap l-gap-m">
        <div class="l-flex-col l-flex__item--1">
            <label for="sp-start-date-input">Start date</label>
            <input
                id="sp-start-date-input"
                ref="startDateInputEl"
                type="text"
                data-datepicker-vue
                data-datepicker-buttontext
                data-datesync-enddate="#sp-end-date-input"
                :data-datepicker-initial-value="filterDateRangeStore.startDate"
                data-datepicker-hide-timetable="true"
            />
        </div>
        <div class="l-flex-col l-flex__item--1">
            <label for="sp-end-date-input">End date</label>
            <input
                id="sp-end-date-input"
                ref="endDateInputEl"
                type="text"
                data-test="sp-filter-date-range-end"
                data-datepicker-vue
                data-datepicker-buttontext
                data-datesync-startdate="#sp-start-date-input"
                :data-datepicker-initial-value="filterDateRangeStore.endDate"
                data-datepicker-hide-timetable="true"
            />
        </div>
    </div>
</template>

<style>
/* we use gap to add spacing, so remove unnecessary margins that are adding extra space */
#sp-start-date-input,
#sp-end-date-input,
.adtp-btn {
    margin: 0;
}
</style>
