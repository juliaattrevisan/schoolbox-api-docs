<script>

/**
 * The status of the submission
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
     * Format the submission status
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
  <div class="status">
    <div class="status__column" v-html="getFormattedStatus(data.submission.status)">
    </div>
    <div class="small-12 medium-6 columns">
      <a class="button icon-approve" :href="data.submission._links.inline_grader">Review in Inline Grader</a>
    </div>
  </div>
</template>

<style lang="scss" style="scoped">

.status {
  display: flex;
}

  .status__column { 
    flex: 1 1 0; //keep both sides equal
  }

</style>
