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
    <div class="container">
        <div class="name">
            <div class="compact card">
                <a data-plugin="preview" :href="data.url" rel="external" target="_blank">
                    <img class="mime" :src="`/static/${data.mime.url}`" height="32" width="32" :title="data.mime.description">
                    <h3>{{ data.name }}</h3>
                </a>
                <p class="meta">Date modified: {{ data.dateModified.formatted }}</p>
            </div>
        </div>
        <nav>
            <a class="icon-modify" :href="data.editUrl" title="Modify File"></a>
        </nav>
    </div>
</template>

<style lang="scss" scoped>

.container {
    display: flex;
    justify-content: space-between;
}

.name {
    margin-right: 1rem;
}

.card { 
    padding: 0; //remove the left/right padding
}

h3 {
    font-size: 0.825rem;
    text-overflow: clip;
    white-space: normal;
}

.meta {
    display: none;
    @media (max-width: 700px) { //show on mobile, as the date modified td will be hidden
        display: block;
    }
}

nav {
    flex: 0 0 2.5rem;
    height: 2.5rem;
    text-align: center;
    width: 2.5rem;
}

nav a {
    color: var(--content-ui-foreground);
    display: block;
    line-height: 2.5;
    opacity: 0.7;
    padding-left: 8px; //move the pencil into the centre
}

</style>