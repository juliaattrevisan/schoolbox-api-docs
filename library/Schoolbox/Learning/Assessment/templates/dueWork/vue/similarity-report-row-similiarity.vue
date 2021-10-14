<script>

/**
 * The similarity of files
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
  <ul class="percentage-list">
    <li v-for="file in data.files" :key="file.id">{{ getFormattedPercentage(file.turnitin.percentage) }}</li>
  </ul>
</template>

<style lang="scss" style="scoped">

.percentage-list {
  list-style-type: none;
  margin: 0;
  li {
    margin-bottom: 0.25rem;
  }
}

</style>
