<script setup lang="ts">
/**
 * A media article, which typically contains content created with CKEditor,
 * so we need to ensure that any media elements within are correctly initialised.
 */

import sanitizeHtml from "sanitize-html";

interface Props {
    content: string; // the content to display
    dataTest?: string; // the data-test attribute
    tag?: string; // when you wish to use a different tag than article
    id?: string; // the id of the element
    class?: string; // the classes to apply to the element
}

const props = withDefaults(defineProps<Props>(), {
    content: "",
    dataTest: undefined,
    tag: "article",
    id: undefined,
    class: undefined,
});

const articleEl = window.Vue.ref<HTMLElement | null>(null);

const allowedAttributes = {
    ...sanitizeHtml.defaults.allowedAttributes,
    oembed: ["url"],
};

/**
 * Ensure any content within it with the
 * `data-schoolboxresource-type` attribute will have the appropriate media
 * player applied to it if it's not applied already.
 */
function initMediaElementPlayer() {
    const $el = window.jQuery(articleEl.value as HTMLElement);
    $.fn.schoolboxResource_ReplaceAll($el);
    window.Vue.nextTick(() => {
        $.fn.schoolboxResource_InitialiseMediaElementPlayer($el);
    });
}

/**
 * Ensure any iframely embeds are displayed correctly
 */
function initIframely() {
    articleEl.value
        ?.querySelectorAll("oembed[url]:not(:has(.iframely-embed)), div[data-oembed-url]:not(:has(.iframely-embed))")
        .forEach((oEmbedEl) => {
            window.iframely.load(oEmbedEl, oEmbedEl.getAttribute("data-oembed-url") || oEmbedEl.getAttribute("url"));
        });
}

/**
 * To properly display any media within the content
 */
window.Vue.onMounted(() => {
    initMediaElementPlayer();
    initIframely();
    console.warn(sanitizeHtml.defaults);
});
</script>

<template>
    <!-- eslint-disable vue/no-v-html | text is scrubbed both on input, and here with sanitize-html -->
    <!-- eslint-disable vue/no-v-text-v-html-on-component | props.tag is passed through  -->
    <component
        :is="props.tag"
        :id="props.id ? props.id : undefined"
        ref="articleEl"
        :class="props.class ? props.class : undefined"
        :data-test="props.dataTest ? props.dataTest : undefined"
        v-html="
            sanitizeHtml(props.content, {
                allowedTags: sanitizeHtml.defaults.allowedTags.concat(['img', 'oembed']),
                allowedAttributes,
            })
        "
    ></component>
    <!-- eslint-enable vue/no-v-html -->
    <!-- eslint-enable vue/no-v-text-v-html-on-component -->
</template>
