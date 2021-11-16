<script>

/**
 * Contains the file name, icon and will show the date on smaller screens
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
    <div >
        <a :href="data.url" rel="external" target="_blank">
            <h3><img class="mime" :src="`/static/${data.mime.url}`" height="32" width="32" :title="data.mime.description"> {{ data.name }}</h3>
        </a>
        <p class="meta">Date modified: {{ data.dateModified.formatted }}</p>
    </div>
</template>

<style lang="scss" scoped>

h3 {
    font-size: 0.825rem;
    margin-bottom: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.meta {
    display: none;
    @media (max-width: 700px) { //show on mobile, as the date modified td will be hidden
        display: block;
    }
}

</style>