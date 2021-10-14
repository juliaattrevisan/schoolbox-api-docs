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
      <img :src="`/portrait.php?id=${data.user.id}&size=square64`" :alt="`profile photo of ${data.user.fullname}`" class="portrait">
    </div>
    <div>
      <template v-if="data.user.id">
        <a :href="`/search/user/${data.user.id}`" title="View profile" class="name">
          {{ data.user.lastname + ', ' + data.user.firstname }}
        </a>
      </template>
      <template v-else>
        <span class="name">{{ data.user.lastname + ', ' + data.user.firstname }}</span>
      </template> 
      <p v-if="data.user.role" class="meta">{{ data.user.role }}</p>
    </div>
  </div>
</template>

<style lang="scss" scoped>

.student {
  display: flex; 
}

  .student__portrait {
    flex-basis: 0 0 64px;
    margin-right: 1rem;
  }

</style>