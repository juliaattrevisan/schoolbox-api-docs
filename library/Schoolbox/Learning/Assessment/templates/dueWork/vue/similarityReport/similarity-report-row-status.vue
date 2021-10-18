<script>

/**
 * The status of the submission and a link to the inline grader
 * 
 */

export default {

  props: {
    data: { //the row data
      type: Object,
      required: true
    },
  },

  methods: {

    /**
     * Format the submission status display label 
     * 
     * @param { String }
     * 
     * @return { String }
     */
    getFormattedStatus (status) {
      const statusOptions = {
        'failed': '<span class="unsubmitted label">Resubmission Required</span>',
        'pending': '<span class="submitted label">Marking Required</span>',
        'reviewed': '<span class="reviewed label">Reviewed</span>',
      }
      return statusOptions[status];
    }

  },

}
</script>

<template>
  <div class="status title-on-single-column" data-title="Status">
    <div class="status__column" v-html="getFormattedStatus(data.submission.status)">
    </div>
    <div class="status__column">
      <a class="button icon-approve" :href="data.submission._links.inline_grader">Review in Inline Grader</a>
    </div>
  </div>
</template>

<style lang="scss" style="scoped">

@import './similarity-report';

.status {
  display: flex;
  @media (max-width: $tableToSingleColumn) {
    flex-direction: column;
  }
}

  .status__column { 
    flex: 1 1 0; //keep both sides equal
    @media (max-width: $tableToSingleColumn) {
      margin-bottom: 0.5rem; //so there's a gap to .button
    }
  }

  .button {
    min-height: 0; //no need for min height in this instance
    padding: 0.5rem 1rem; //add a bit more space left and right of the button
  }

</style>
