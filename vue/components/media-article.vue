<script setup lang="ts">
/**
 * Media article: on initialization, any content within it with the
 * `data-schoolboxresource-type` attribute will have the appropriate media
 * player applied to it if it's not applied already.
 */

interface Props {
    content: string; // the content to display
    dataTest?: string; // the data-test attribute
}

const props = defineProps<Props>();
const articleEl = window.Vue.ref<HTMLElement | null>(null);

/**
 * To apply the media player to any content within the article
 */
// window.Vue.onMounted(() => {
//     const $el = window.jQuery(articleEl.value as HTMLElement);
//     $.fn.schoolboxResource_ReplaceAll($el);
//     window.Vue.nextTick(() => {
//         $.fn.schoolboxResource_InitialiseMediaElementPlayer($el);
//     });
// });
</script>

<template>
    <!-- eslint-disable-next-line vue/no-v-html | text is scrubbed -->
    <article ref="articleEl" v-html="props.content" :data-test="props.dataTest ? props.dataTest : undefined"></article>
</template>
