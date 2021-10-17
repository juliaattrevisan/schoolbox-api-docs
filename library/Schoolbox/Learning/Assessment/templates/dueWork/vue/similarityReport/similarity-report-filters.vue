<script>

/**
 * Contains the similarity report filters, score and status
 */

import { Event } from 'vue-tables-2';

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
     * 
     */
    onChange (isChecked) {
      if (this.isFailedChecked) {
        this.isFailedChecked = false;
        Event.$emit('vue-tables.filter::HIDE_FAILED', 'failed');
      } else {
        this.isFailedChecked = true;
        Event.$emit('vue-tables.filter::SHOW_FAILED', 'query');
      }
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
            <input type="checkbox" id="resubmission-required" value="failed" :checked="isFailedChecked" @change="onChange($event.target.checked)">
            <label for="resubmission-required">Resubmission required</label>
          </li>
          <li>
            <input type="checkbox" id="marking-required" :checked="isMarkingRequiredChecked" value="marking-required">
            <label for="marking-required">Marking required</label>
          </li>
          <li>
            <input type="checkbox" id="reviewed" :checked="isReviewedChecked" value="reviewed">
            <label for="reviewed">Reviewed</label>
          </li>
        </ul>
      </div>
    </div>
  </div>

</template>

<style lang="scss" scoped>


</style>