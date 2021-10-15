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
     * Display the file name with the similairty score added
     * 
     * @param { Object }
     * 
     * @returns { String }
     */
    getFileTitle (file) {
      return `${Math.round(file.turnitin.percentage)}% - ${file.name}.${file.ext}`;
    }

  },

}
</script>

<template>
  <ul class="list">
    <li v-for="file in data.files" :key="file.id">
      <a :href="file._links.turnitin_report" title="View similarity report">
        {{ getFileTitle(file) }}
      </a>
    </li>
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
