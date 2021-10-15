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
     * round it up and add percent
     */
    getFormattedPercentage (percentage) {
      return Math.round(percentage) + '%';
    }

  },

}
</script>

<template>
  <div>
    <div class="small-12 medium-6 columns">
      <span>{{ data.submission.status }}</span>
    </div>
    <div class="small-12 medium-6 columns">
      <a :href="data.submission._links.inline_grader">Review in Inline Grader</a>
    </div>
  </div>
</template>

<style lang="scss" style="scoped">


</style>
