<script>

/**
 * Contains the similarity report filters, score and status
 */

import { Event } from 'vue-tables-2';
import FilterEvents from './similarityReportFilterEvents';

export default {

  data () {
    return {
      isResubmissionRequiredChecked: true,
      isMarkingRequiredChecked: true,
      isReviewedChecked: true,
    }
  },

  methods: {
    
    /**
     * Toggle the display of submissions that are marked as resubmission required
     * If it's checked, it's shown, so there's no filter applied, otherwise any 
     * rows with status 'failed' will be hidden
     * 
     * @param { Boolean } the status of the checkbox
     */
    onResubmissionRequiredChange (isChecked) {
      this.isResubmissionRequiredChecked = isChecked;
      const filterParam = this.isResubmissionRequiredChecked ? '' : 'failed'; 
      Event.$emit(`vue-tables.filter::${FilterEvents.RESUBMISSION_REQUIRED}`, filterParam); //picked up by similarity-report.vue 
    },

    /**
     * Toggle the display of submissions that are marked as marking required
     * If it's checked, it's shown, so there's no filter applied, otherwise any 
     * rows with status 'pending' will be hidden
     * 
     * @param { Boolean } the status of the checkbox
     */
    onMarkingRequiredChange (isChecked) {
      this.isMarkingRequiredChecked = isChecked;
      const filterParam = this.isMarkingRequiredChecked ? '' : 'pending'; 
      Event.$emit(`vue-tables.filter::${FilterEvents.MARKING_REQUIRED}`, filterParam); //picked up by similarity-report.vue 
    },

    /**
     * Toggle the display of submissions that are marked as reviewed
     * If it's checked, it's shown, so there's no filter applied, otherwise any 
     * rows with status 'reviewed' will be hidden
     * 
     * @param { Boolean } the status of the checkbox
     */
    onReviewedChange (isChecked) {
      this.isReviewedChecked = isChecked;
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
        <select>
          <option value="">-</option>
          <option value="">&gt;= 40%</option>
          <option value="">50%</option>
          <option value="">60%</option>
          <option value="">70%</option>
          <option value="">80%</option>
          <option value="">90%</option>
        </select>
    </div>
    <div class="small-12 medium-6 columns">
      <div class="checklist">
        <legend>Status</legend>
        <ul class="option-list">
          <li>
            <input type="checkbox" id="resubmission-required" :checked="isResubmissionRequiredChecked" @change="onResubmissionRequiredChange($event.target.checked)">
            <label for="resubmission-required">Resubmission required</label>
          </li>
          <li>
            <input type="checkbox" id="marking-required" :checked="isMarkingRequiredChecked" @change="onMarkingRequiredChange($event.target.checked)">
            <label for="marking-required">Marking required</label>
          </li>
          <li>
            <input type="checkbox" id="reviewed" :checked="isReviewedChecked" @change="onReviewedChange($event.target.checked)">
            <label for="reviewed">Reviewed</label>
          </li>
        </ul>
      </div>
    </div>
  </div>

</template>

<style lang="scss" scoped>


</style>