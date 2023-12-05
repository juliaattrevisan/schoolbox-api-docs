<script setup lang="ts">
/**
 * To display the search results image or icon, wrapping the image in a link if there is one
 */

import { Result } from "../../js/types/Result";
import { ResultTypes } from "../../js/types/ResultTypes";

interface Props {
    result: Result; // the result to display
    isSubResult?: boolean; // whether this is a sub result
}
const props = withDefaults(defineProps<Props>(), {
    isSubResult: false,
});

const emit = defineEmits<{
    badgeSelected: [value: Result];
}>();

/**
 * Whether to use a rounded image, currently only for users
 */
const isImgRounded = window.Vue.computed(() => {
    if (props.result.type.includes(ResultTypes.USER)) {
        return true;
    }
    return false;
});

/**
 * Get the image or icon, to avoid repeating in the template
 * @returns {string} the image or icon as html
 */
function getImg() {
    if (props.result.image) {
        return `<img
                    src="${props.result.image}"
                    :alt="Photo of ${props.result.title}"
                />`;
    }
    return `<div class="icon icon-${props.result.icon as string}"></div>`;
}
</script>

<template>
    <div
        v-if="props.result.image || props.result.icon"
        :class="`c-img ${isImgRounded ? 'c-img--round' : ''} ${props.isSubResult ? 'c-img--s' : ''}`"
    >
        <div class="c-img__wrapper">
            <a
                v-if="props.result.type === ResultTypes.BADGE"
                href="#open-badge-modal"
                @click.prevent="emit('badgeSelected', props.result)"
                v-html="getImg()"
            >
            </a>
            <a v-else-if="props.result.url" :href="props.result.url" v-html="getImg()"> </a>
            <div v-else v-html="getImg()"></div>
        </div>
    </div>
</template>

<style scoped></style>
