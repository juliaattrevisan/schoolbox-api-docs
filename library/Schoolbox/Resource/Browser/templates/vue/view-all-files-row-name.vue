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

    mounted () {
        this.checkForPreviewLinks();
    },

    methods: {

        /**
         * Ensure the link uses the pop up modal
         */
        checkForPreviewLinks () {
            $(this.$el.querySelector('[data-plugin="preview"]')).preview();
        },

    },
    
}
</script>

<template>
    <div>
        <a data-plugin="preview" :href="data.url" rel="external">
            <h3 :style="{ opacity: data.hidden ? '0.3' : '1' }">
                <img class="mime" :src="`/static/${data.mime.url}`" height="32" width="32" :title="data.mime.description"> 
                {{ data.name }}
            </h3>
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
    @media (max-width: 700px) { //show on mobile, as the .date-modified-td will be hidden
        display: block;
    }
}

</style>