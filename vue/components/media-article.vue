<script setup lang="ts">
/**
 * Media article, typically contains content created with CKEditor:
 * On initialization, any content within it with the
 * `data-schoolboxresource-type` attribute will have the appropriate media
 * player applied to it if it's not applied already.
 */

interface Props {
    content: string; // the content to display
    dataTest?: string; // the data-test attribute
    tag?: string; // when you wish to use a different tag than article
}

const props = withDefaults(defineProps<Props>(), {
    dataTest: undefined,
    tag: "article",
});

const articleEl = window.Vue.ref<HTMLElement | null>(null);

/**
 * To apply the media player to any content within the article
 */
window.Vue.onMounted(() => {
    const $el = window.jQuery(articleEl.value as HTMLElement);
    $.fn.schoolboxResource_ReplaceAll($el);
    window.Vue.nextTick(() => {
        $.fn.schoolboxResource_InitialiseMediaElementPlayer($el);
    });
});
</script>

<template>
    <!-- eslint-disable vue/no-v-html | text is scrubbed -->
    <!-- eslint-disable vue/no-v-text-v-html-on-component | props.tag is passed through  -->
    <component
        :is="props.tag"
        ref="articleEl"
        :data-test="props.dataTest ? props.dataTest : undefined"
        v-html="props.content"
    ></component>
    <!-- eslint-enable vue/no-v-html -->
    <!-- eslint-enable vue/no-v-text-v-html-on-component -->
</template>
