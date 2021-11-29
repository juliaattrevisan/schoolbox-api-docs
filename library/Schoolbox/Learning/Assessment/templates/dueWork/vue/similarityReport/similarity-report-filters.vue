<script>

/**
 * Contains the similarity report filters, score and status
 */

import { Event } from 'vue-tables-2-premium';
import FilterEvents from './similarityReportFilterEvents';

export default {

    data () {
        return {
            similarityScoreSelection: 0,
            isResubmissionRequiredChecked: true,
            isMarkingRequiredChecked: true,
            isReviewedChecked: true,
        }
    },

    mounted () {
        this.initResetFiltersListener();
    },

    methods: {
        
        /**
         * Reset all the filters back to there base level
         */
        initResetFiltersListener () {
            document.addEventListener(FilterEvents.RESET_FILTERS, () => {
                this.similarityScoreSelection = 0;
                this.isResubmissionRequiredChecked = true;
                this.isMarkingRequiredChecked = true;
                this.isReviewedChecked = true;
                this.onSimilarityScoreChange();
                this.onResubmissionRequiredChange();
                this.onMarkingRequiredChange();
                this.onReviewedChange();
            });
        },

        /**
         * Show any rows that contain similarity scores above the selection
         * Note that for rows with multiple files, only a single file with a similarity
         * score above is required
         */
        onSimilarityScoreChange () {
            Event.$emit(`vue-tables.filter::${FilterEvents.SIMILARITY_SCORE}`, this.similarityScoreSelection); //picked up by similarity-report.vue 
            this.$nextTick(() => { //to ensure the latest filtered rows are available, dispatched to similarity-report-row-similarity.vue and similarity-report.vue 
                document.dispatchEvent(new CustomEvent(FilterEvents.SIMILARITY_SCORE, { detail: { score: this.similarityScoreSelection } } ) );
            });
        },

        /**
         * Toggle the display of submissions that are marked as resubmission required
         * If it's checked, it's shown, so there's no filter applied, otherwise any 
         * rows with status 'failed' will be hidden
         */
        onResubmissionRequiredChange () {
            const filterParam = this.isResubmissionRequiredChecked ? '' : 'failed'; 
            Event.$emit(`vue-tables.filter::${FilterEvents.RESUBMISSION_REQUIRED}`, filterParam); //picked up by similarity-report.vue 
            this.$nextTick(() => document.dispatchEvent(new CustomEvent(FilterEvents.RESUBMISSION_REQUIRED))); //picked up by similarity-report.vue 
        },

        /**
         * Toggle the display of submissions that are marked as marking required
         * If it's checked, it's shown, so there's no filter applied, otherwise any 
         * rows with status 'pending' will be hidden
         */
        onMarkingRequiredChange () {
            const filterParam = this.isMarkingRequiredChecked ? '' : 'pending'; 
            Event.$emit(`vue-tables.filter::${FilterEvents.MARKING_REQUIRED}`, filterParam); //picked up by similarity-report.vue 
            this.$nextTick(() => document.dispatchEvent(new CustomEvent(FilterEvents.MARKING_REQUIRED))); //picked up by similarity-report.vue 
        },

        /**
         * Toggle the display of submissions that are marked as reviewed
         * If it's checked, it's shown, so there's no filter applied, otherwise any 
         * rows with status 'reviewed' will be hidden
         */
        onReviewedChange () {
            const filterParam = this.isReviewedChecked ? '' : 'reviewed'; 
            Event.$emit(`vue-tables.filter::${FilterEvents.REVIEWED}`, filterParam); //picked up by similarity-report.vue 
            this.$nextTick(() => document.dispatchEvent(new CustomEvent(FilterEvents.REVIEWED))); //picked up by similarity-report.vue 
        },

    }

}
</script>

<template>
    <div class="filters">
        <div class="filters__column">
            <label>Similarity score</label>
            <select v-model="similarityScoreSelection" @change="onSimilarityScoreChange()">
                <option value="0">-</option>
                <option value="90">Above 90%</option>
                <option value="80">Above 80%</option>
                <option value="70">Above 70%</option>
                <option value="60">Above 60%</option>
                <option value="50">Above 50%</option>
                <option value="40">Above 40%</option>
                <option value="30">Above 30%</option>
                <option value="20">Above 20%</option>
                <option value="10">Above 10%</option>
            </select>
        </div>
        <div class="filters__column">
            <div class="checklist">
                <label>Status</label>
                <ul class="option-list">
                    <li>
                        <input type="checkbox" id="resubmission-required" v-model="isResubmissionRequiredChecked" @change="onResubmissionRequiredChange()">
                        <label for="resubmission-required">Resubmission required</label>
                    </li>
                    <li>
                        <input type="checkbox" id="marking-required" v-model="isMarkingRequiredChecked" @change="onMarkingRequiredChange()">
                        <label for="marking-required">Marking required</label>
                    </li>
                    <li>
                        <input type="checkbox" id="reviewed" v-model="isReviewedChecked" @change="onReviewedChange()">
                        <label for="reviewed">Reviewed</label>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>

@import './similarity-report';

.filters {
    background: white;
    display: flex;
    padding: 1rem 0;
    @media (max-width: $tableToSingleColumn) {
        flex-direction: column;
    }
}

    .filters__column { 
        flex: 1 1 0; //keep both sides equal
        margin: 0 1rem;
    }

legend { 
    margin-bottom: 0.5rem; //extra gap between it and the form element below
}

.option-list li {
    display: inline-block; //rather than block, to line up with similarity score filter better
    margin-right: 2rem;
    @media (max-width: $tableToSingleColumn) {
        display: block;
    }
}

</style>