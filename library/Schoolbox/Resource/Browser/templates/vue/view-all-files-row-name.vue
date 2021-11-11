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
        <div class="list-item">
            <div class="compact card small-12">
                <a data-plugin="preview" :href="data.url" rel="external" target="_blank">
                    <img class="mime" :src="`/static/images/mime/application-pdf.svg`" height="64" width="64" :title="data.mime.description">
                    <h3>{{ data.name }}</h3>
                </a>
            </div>
        </div>
        <nav>
            <a class="icon-modify" :href="data.edit_url" title="Modify File"></a>
        </nav>
    </div>
</template>

<style lang="scss" scoped>

.card.compact { //remove the left/right padding
    padding: 0;
}

</style>