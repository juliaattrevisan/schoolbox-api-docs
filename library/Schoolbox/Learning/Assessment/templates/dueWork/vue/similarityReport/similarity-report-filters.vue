<script>

/**
 * Contains the similarity report filters, score and status
 */

import { Event } from 'vue-tables-2';
import FilterEvents from './similarityReportFilterEvents';

export default {

  data () {
    return {
      similarityScoreSelection: '',
      isResubmissionRequiredChecked: true,
      isMarkingRequiredChecked: true,
      isReviewedChecked: true,
    }
  },

  methods: {
    
    /**
     * Show any rows that contain similarity scores above the selection
     * Note that for rows with multiple files, only a single file with a similarity
     * score above is required
     */
    onSimilarityScoreChange () {
      Event.$emit(`vue-tables.filter::${FilterEvents.SIMILARITY_SCORE}`, this.similarityScoreSelection); //picked up by similarity-report.vue 
    },

    /**
     * Toggle the display of submissions that are marked as resubmission required
     * If it's checked, it's shown, so there's no filter applied, otherwise any 
     * rows with status 'failed' will be hidden
     */
    onResubmissionRequiredChange () {
      const filterParam = this.isResubmissionRequiredChecked ? '' : 'failed'; 
      Event.$emit(`vue-tables.filter::${FilterEvents.RESUBMISSION_REQUIRED}`, filterParam); //picked up by similarity-report.vue 
    },

    /**
     * Toggle the display of submissions that are marked as marking required
     * If it's checked, it's shown, so there's no filter applied, otherwise any 
     * rows with status 'pending' will be hidden
     */
    onMarkingRequiredChange () {
      const filterParam = this.isMarkingRequiredChecked ? '' : 'pending'; 
      Event.$emit(`vue-tables.filter::${FilterEvents.MARKING_REQUIRED}`, filterParam); //picked up by similarity-report.vue 
    },

    /**
     * Toggle the display of submissions that are marked as reviewed
     * If it's checked, it's shown, so there's no filter applied, otherwise any 
     * rows with status 'reviewed' will be hidden
     */
    onReviewedChange () {
      const filterParam = this.isReviewedChecked ? '' : 'reviewed'; 
      Event.$emit(`vue-tables.filter::${FilterEvents.REVIEWED}`, filterParam); //picked up by similarity-report.vue 
    },

  }

}
</script>

<template>

  <div class="content">
    <div class="small-12 medium-6 columns">
      <legend>Similarity score</legend>
        <select v-model="similarityScoreSelection" @change="onSimilarityScoreChange($event.target.value)">
          <option value="">-</option>
          <option value="40">Above 40%</option>
          <option value="50">Above 50%</option>
          <option value="60">Above 60%</option>
          <option value="70">Above 70%</option>
          <option value="80">Above 80%</option>
          <option value="90">Above 90%</option>
        </select>
    </div>
    <div class="small-12 medium-6 columns">
      <div class="checklist">
        <legend>Status</legend>
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


</style>