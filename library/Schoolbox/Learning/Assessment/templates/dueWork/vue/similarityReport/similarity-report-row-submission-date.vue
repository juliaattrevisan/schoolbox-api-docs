<script>

/**
 * The submission date with the relative date in the title attribute
 * 
 */

export default {

  props: {
    data: { //the row data
      type: Object,
      required: true
    },
  },

}
</script>

<template>
  <div class="title-on-single-column" data-title="Submission date"> 
    <span :title="data.submission.submitted_at.relative">{{ data.submission.submitted_at.nice }}</span>
  </div>
</template>

<style lang="scss" scoped>
@import './similarity-report';
</style>