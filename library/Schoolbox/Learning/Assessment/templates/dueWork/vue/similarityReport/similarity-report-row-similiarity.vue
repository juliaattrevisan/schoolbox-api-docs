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
      if (this.isBetween(percentageRounded, 0, 50)) {
        return '#4fa74f';
      } else if (this.isBetween(percentageRounded, 50, 59)) {
        return '#7a904d';
      } else if (this.isBetween(percentageRounded, 60, 69)) {
        return '#9f7c4b';
      } else if (this.isBetween(percentageRounded, 70, 79)) {
        return '#ba6d4a';
      } else if (this.isBetween(percentageRounded, 80, 89)) {  
        return '#da5c49';  
      } else if (this.isBetween(percentageRounded, 90, 100)) {  
        return '#fa4a47';
      }
    },

    /**
     * Check if x is between a min and max
     * 
     * @param { Number }
     * @param { Number }
     * @param { Number }
     * 
     * @returns { Boolean }
     */
    isBetween (x, min, max) {
      return x >= min && x <= max;
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
