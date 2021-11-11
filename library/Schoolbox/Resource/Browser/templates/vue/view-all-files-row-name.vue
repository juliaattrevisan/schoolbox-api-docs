<script>

/**
 * Contains the file name, icon and edit links
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
    <div class="actions-small-1">
        <div>
            <div class="compact card">
                <a data-plugin="preview" :href="data.url" rel="external" target="_blank">
                    <img class="mime" :src="`/static/${data.mime.url}`" height="32" width="32" :title="data.mime.description">
                    <h3>{{ data.name }}</h3>
                </a>
                <p class="meta">Date modified: {{ data.dateModified.formatted }}</p>
            </div>
        </div>
        <nav>
            <a class="icon-modify" :href="data.edit_url" title="Modify File"></a>
        </nav>
    </div>
</template>

<style lang="scss" scoped>

.card { 
    padding: 0; //remove the left/right padding
}

h3 {
    font-size: 0.825rem;
}

.meta {
    display: none;
    @media (max-width: 480px) { //show on mobile, as the date modified td will be hidden
        display: block;
    }
}

</style>