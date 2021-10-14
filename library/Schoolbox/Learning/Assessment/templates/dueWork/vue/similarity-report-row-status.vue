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
    Status
  </div>
</template>

<style lang="scss" style="scoped">


</style>
