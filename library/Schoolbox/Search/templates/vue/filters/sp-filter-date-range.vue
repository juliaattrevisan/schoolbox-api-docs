<script setup lang="ts">
/**
 * To filter the search results to a date range
 */

import { useFilterDateRangeStore } from "./store/spFilterDateRangeStore";

const filterDateRangeStore = useFilterDateRangeStore();

/**
 * initialise the date pickers
 */
function initDatePickers() {
    $("[data-datepicker]").each(function attachAlaressDatePicker() {
        const $dateinput = $(this);
        if ($dateinput.attr("data-datepicker-id") === undefined) {
            $dateinput.alaressDateTimePicker();
        }
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
                    data-datepicker
                    data-datepicker-buttontext
                    data-datesync-enddate="#sp-end-date-input"
                />
            </div>
            <div class="input-group">
                <input
                    id="sp-end-date-input"
                    type="text"
                    data-datepicker
                    data-datepicker-buttontext
                    data-datesync-startdate="#sp-start-date-input"
                />
            </div>
        </div>
    </div>
</template>

<style scoped></style>
