<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * Add a filter reset button to the no results display
 * This template is initiated from similarityReport.js
 * https://matanya.gitbook.io/vue-tables-2/custom-template
 */

import FilterEvents from "./similarityReportFilterEvents";

export default {
    props: {
        props: {
            type: Object,
            required: true,
        },
    },

    methods: {
        /**
         * Reset all the filters, dispatched to similarity-report-filters.vue
         */
        onResetSelected() {
            document.dispatchEvent(new CustomEvent(FilterEvents.RESET_FILTERS));
        },
    },
};
</script>

<template>
    <tr class="VueTables__no-results">
        <td class="text-center" :tabindex="props.tabIndex" :colspan="props.colspan">
            No submissions match your current filters.
            <button @click.prevent="onResetSelected" @keydown.enter="onResetSelected" class="button-reset">
                Reset filters
            </button>
            to show all submissions.
        </td>
    </tr>
</template>

<style lang="scss" scoped>
.button-reset {
    background: none;
    font-size: 0.8rem;
    padding: 0px;
    vertical-align: baseline;
    &:hover {
        background: none;
    }
}
</style>
