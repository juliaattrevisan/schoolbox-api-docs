<script>

/**
 * The submission date
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
  <span :title="data.submission.submitted_at.relative">{{ data.submission.submitted_at.nice }}</span>
</template>
