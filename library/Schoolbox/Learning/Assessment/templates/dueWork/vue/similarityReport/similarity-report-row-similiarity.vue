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
     * Display the file name with the similairty score prepended
     * 
     * @param { Object }
     * 
     * @returns { String }
     */
    getFileTitle (file) {
      return `${Math.round(file.turnitin.percentage)}% - ${file.name}.${file.ext}`;
    },

    /**
     * Add a colour to represent the similarity score
     * A low score starting with green to high score red is used 
     * 
     * @param { Object }
     * 
     * @returns { String }
     */
    getFileColour (file) {
      const percentageRounded = Math.round(file.turnitin.percentage);
      if (percentageRounded < 50) {
        return '#4fa74f';
      } else if (percentageRounded >= 50 && percentageRounded <= 59) {
        return '#7a904d';
      } else if (percentageRounded >= 60 && percentageRounded <= 69) {
        return '#9f7c4b';
      } else if (percentageRounded >= 70 && percentageRounded <= 79) {
        return '#ba6d4a';
      } else if (percentageRounded >= 80 && percentageRounded <= 89) {  
        return '#da5c49';  
      } else if (percentageRounded >= 90) {  
        return '#fa4a47';
      }
    },

  },

}
</script>

<template>
  <ul class="list">
    <li v-for="file in data.files" :key="file.id">
      <a :href="file._links.turnitin_report" :style="{ color: getFileColour(file) }" title="View similarity report">
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
