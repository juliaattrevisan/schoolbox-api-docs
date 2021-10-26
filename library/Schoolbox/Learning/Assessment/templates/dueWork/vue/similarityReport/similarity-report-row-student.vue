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
    <div class="student title-on-single-column" data-title="Student">
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

@import './similarity-report';

.student {
    display: flex;
    @media (max-width: $tableToSingleColumn) {
        flex-wrap: wrap; //so .title-on-single-column can be on its own row
    }
}

.student__portrait {
    margin: 0 1rem 0.5rem 0;
    flex: 0 0 32px; //32px is the size of the image, so only need occupy this space
}

.student__name {
    h3 {
        margin-top: 0.25rem;
        margin-bottom: 0rem; //prevent any extra space at the bottom of rows
    }
}

</style>