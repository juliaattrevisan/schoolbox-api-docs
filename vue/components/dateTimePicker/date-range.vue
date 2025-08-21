<script setup lang="ts">
/**
 * Two linked date pickers to select a date range
 */

import type { ModelRef } from "vue";
import { EMPTY_DATE, INVALID_DATE } from "./datePickerTypes";

interface Props {
    idPrefix: string; // unique identifier for this date range picker
    dataTestPrefix?: string; // optional prefix for data-test attributes, defaults to idPrefix
}

const props = defineProps<Props>();
const startDate = defineModel<string>("startDate", { default: "" });
const endDate = defineModel<string>("endDate", { default: "" });

const startDateInputEl = window.Vue.ref<HTMLInputElement | null>(null);
const endDateInputEl = window.Vue.ref<HTMLInputElement | null>(null);

/**
 * Ensure the error message is hidden on clear filters
 * @param selector - The selector for the error message
 */
function clearErrorMessage(selector: string): void {
    const dateError = document.querySelector(selector) as HTMLDivElement;
    if (dateError) {
        dateError.style.display = "none";
    }
}

/**
 * Clear the start date if the filters have been reset
 */
window.Vue.watch(startDate, (newValue) => {
    if (newValue === EMPTY_DATE) {
        if (startDateInputEl.value) {
            startDateInputEl.value.value = EMPTY_DATE;
        }
        clearErrorMessage(`#${props.idPrefix}-start-date-input-error`);
    }
});

/**
 * Clear the end date if the filters have been reset
 */
window.Vue.watch(endDate, (newValue) => {
    if (newValue === EMPTY_DATE) {
        if (endDateInputEl.value) {
            endDateInputEl.value.value = EMPTY_DATE;
        }
        clearErrorMessage(`#${props.idPrefix}-end-date-input-error`);
    }
});

/**
 * Formats the date picker date to ISO8601 format
 * @param date - The date to format
 * @returns - The formatted date
 */
function getDateFormatted(date: string): string {
    return window.dayjs(date, window.dateDisplayFormats.dayjs).format();
}

/**
 * When an input is changed, update the model
 * @param value - The value of the input
 * @param model - The model to update
 */
function onInputChange(value: string, model: ModelRef<string>): void {
    if (value === EMPTY_DATE) {
        model.value = EMPTY_DATE;
    } else {
        model.value = getDateFormatted(value);
    }
}

/**
 * [data-datepicker-vue] is used to avoid using the attach within
 * www/static/js/alaress-datetime-picker/attacher.datepicker.js as it may be
 * called when the component is not mounted yet.
 */
function initDatePickers(): void {
    $("[data-datepicker-vue]").each(function attachAlaressDatePicker() {
        const $dateInput = $(this);
        if ($dateInput.attr("data-datepicker-id") === undefined) {
            $dateInput.alaressDateTimePicker();
        }
    });

    if (!startDateInputEl.value || !endDateInputEl.value) {
        throw new Error("Date input elements not found");
    }

    $(startDateInputEl.value).on("change", (event) => onInputChange(event.target.value, startDate));
    $(endDateInputEl.value).on("change", (event) => onInputChange(event.target.value, endDate));
}

window.Vue.onMounted(() => {
    initDatePickers();
});

/**
 * Clear the invalid date input and reset the model if either input is invalid
 * This can be used if the parent component runs an action that should reflect the current state
 * i.e. when searching if it's invalid we don't search with the date ranges set, so the UI should reflect that.
 */
function clearInvalidDates(): void {
    if (startDate.value === INVALID_DATE) {
        startDate.value = EMPTY_DATE;
    }
    if (endDate.value === INVALID_DATE) {
        endDate.value = EMPTY_DATE;
    }
}

defineExpose({ clearInvalidDates });
</script>

<template>
    <div class="l-flex-row l-flex--no-wrap l-gap-m">
        <div class="l-flex-col l-flex__item--1">
            <label :for="`${props.idPrefix}-start-date-input`">Start date</label>
            <input
                :id="`${props.idPrefix}-start-date-input`"
                ref="startDateInputEl"
                type="text"
                :data-test="`${props.dataTestPrefix || props.idPrefix}-date-range-start`"
                data-datepicker-vue
                data-datepicker-buttontext
                :data-datesync-enddate="`#${props.idPrefix}-end-date-input`"
                :data-datepicker-initial-value="startDate"
                data-datepicker-hide-timetable="true"
            />
        </div>
        <div class="l-flex-col l-flex__item--1">
            <label :for="`${props.idPrefix}-end-date-input`">End date</label>
            <input
                :id="`${props.idPrefix}-end-date-input`"
                ref="endDateInputEl"
                type="text"
                :data-test="`${props.dataTestPrefix || props.idPrefix}-date-range-end`"
                data-datepicker-vue
                data-datepicker-buttontext
                :data-datesync-startdate="`#${props.idPrefix}-start-date-input`"
                :data-datepicker-initial-value="endDate"
                data-datepicker-hide-timetable="true"
            />
        </div>
    </div>
</template>

<style scoped>
/* we use gap to add spacing, so remove unnecessary margins that are adding extra space */
input,
:deep(.adtp-btn) {
    margin: 0;
}
</style>
