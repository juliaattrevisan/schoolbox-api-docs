<script>

/**
 * The name and link to the users profile page
 */

export default {
    
    props: {
        data: {
            type: Object,
            required: true,
        },
        index: {
            type: Number,
            required: true
        }
    }

}

</script>

<template>
    <div class="user">
        <img :src="`/portrait.php?id=${data.id}&size=square64`" :alt="`profile photo of ${data.fullName}`" class="user-image">
        <template v-if="data.id">
            <a :href="`/search/user/${data.id}`" title="View profile" class="user-name">
                {{ data.lastName + ', ' + data.firstName }}
            </a>
        </template>
        <template v-else>
            <span class="user-name">{{ data.lastName + ', ' + data.firstName }}</span>
        </template> 
    </div>
</template>

<style lang="scss">

.user {
    display: flex;
}

// .user-image, .user-name {
    // display: inline-block;
    // vertical-align: top;
// }

.user-image {
    margin-right: 1rem;
}

</style>