<script>

/**
 * Contains the link to edit the file
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
    <nav>
        <a class="icon-modify" :href="data.editUrl" title="Modify File"></a>
    </nav>
</template>

<style lang="scss" scoped>

nav {
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