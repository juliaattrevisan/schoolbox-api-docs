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
  <ul class="list">
    <li v-for="file in data.files" :key="file.id">{{ getFormattedPercentage(file.turnitin.percentage) }}</li>
    <!-- <li v-for="file in data.files" :key="file.id">{{ file.name }}</li> -->
  </ul>
</template>

<style lang="scss" style="scoped">

.list {
  list-style-type: none;
  margin: 0;
  li {
    margin-bottom: 0.25rem;
  }
}

</style>
