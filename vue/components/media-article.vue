<script>
/**
 * Media article: on initialization, any content within it with the
 * `data-schoolboxresource-type` attribute will have the appropriate media
 * player applied to it.
 */

export default {
    name: "media-article",
    computed: {
        jQuery: () => window.jQuery,
    },
    mounted() {
        let $el = this.jQuery(this.$el)
        this.jQuery.fn.schoolboxResource_ReplaceAll($el);
        this.$nextTick(() => {
            this.jQuery.fn.schoolboxResource_InitialiseMediaElementPlayer($el);
        });
    },
}
</script>

<template>
    <article>
        <slot></slot>
    </article>
</template>
