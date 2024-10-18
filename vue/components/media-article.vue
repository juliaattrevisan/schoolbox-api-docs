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
    sanitize?: boolean; // the sanitizeHtml function to use
}

const props = withDefaults(defineProps<Props>(), {
    content: "",
    dataTest: undefined,
    tag: "article",
    id: undefined,
    class: undefined,
    sanitize: undefined,
});

const articleEl = window.Vue.ref<HTMLElement | null>(null);

/**
 * Get the content to display, optionally sanitising it.
 * Currently sanitizeHtml is only used for:
 * library/Schoolbox/Learning/Learnosity/templates/vue/student/student-item-question-display.vue
 * As this is coming from Learnosity directly to the front-end
 * @returns the content to display
 */
function getContent(): string {
    if (props.sanitize) {
        return sanitizeHtml(props.content, {
            allowedTags: sanitizeHtml.defaults.allowedTags.concat(["img"]), // allow img tags
        });
    }
    return props.content;
}

/**
 * Ensure any content within it with the
 * `data-schoolboxresource-type` attribute will have the appropriate media
 * player applied to it if it's not applied already.
 */
async function initMediaElementPlayer(): Promise<void> {
    const $el = window.jQuery(articleEl.value as HTMLElement);
    // @ts-expect-error - schoolboxResource is available here, but untyped.
    // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
    $.fn.schoolboxResource_ReplaceAll($el);

    await window.Vue.nextTick(() => {
        // @ts-expect-error - schoolboxResource is available here, but untyped.
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
        $.fn.schoolboxResource_InitialiseMediaElementPlayer($el);
    });
}

/**
 * Ensure any iframely embeds are displayed correctly
 */
function initIframely(): void {
    articleEl.value
        ?.querySelectorAll("oembed[url]:not(:has(iframe)), div[data-oembed-url]:not(:has(iframe))")
        .forEach((oEmbedEl) => {
            window.iframely.load(
                oEmbedEl,
                oEmbedEl.getAttribute("data-oembed-url") || oEmbedEl.getAttribute("url") || undefined,
            );
        });
}

/**
 * To properly display any media within the content
 */
window.Vue.onMounted(async () => {
    // @ts-expect-error Sometimes $.fn.schoolboxResource_ReplaceAll is not defined yet, and causes this to fail loading
    // This checks if it's undefined and waits 100ms before trying again if so
    if ($.fn.schoolboxResource_ReplaceAll === undefined) {
        console.warn("schoolboxResource_ReplaceAll is not defined, trying again in 100ms");
        await new Promise((resolve) => {
            setTimeout(resolve, 100);
        });
    }
    void initMediaElementPlayer();
    initIframely();
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
        v-html="getContent()"
    ></component>
    <!-- eslint-enable vue/no-v-html -->
    <!-- eslint-enable vue/no-v-text-v-html-on-component -->
</template>
