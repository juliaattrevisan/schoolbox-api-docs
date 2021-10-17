<script>

/**
 * Contains the students profile, name and role 
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
  <div class="student">
    <div class="student__portrait">
      <img :src="`/portrait.php?id=${data.user.id}&size=square32`" :alt="`profile photo of ${data.user.fullname}`" class="portrait">
    </div>
    <div class="student__name">
      <template v-if="data.user.id">
        <a :href="`/search/user/${data.user.id}`" title="View profile" class="name">
          <h3>{{ data.user.lastname + ', ' + data.user.firstname }}</h3>
        </a>
      </template>
      <template v-else>
        <h3>{{ data.user.lastname + ', ' + data.user.firstname }}</h3>
      </template> 
    </div>
  </div>
</template>

<style lang="scss" scoped>

.student {
  display: flex; 
}

  .student__portrait {
    flex-basis: 0 0 32px; //32px is the size of the image, so only need occupy this space
    margin-right: 1rem;
  }

  .student__name {
    h3 {
      margin-bottom: 0rem; //prevent any extra space at the bottom of rows
    }
  }

</style>