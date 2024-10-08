<script setup lang="ts">
/**
 * To display the search results image or icon, wrapping the image in a link if there is one
 */

import type { Result } from "../../js/types/Result";
import { ResultTypes } from "../../js/types/ResultTypes";
import SpResultsResultImageWrapper from "./sp-results-result-image-wrapper.vue";

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
</script>

<template>
    <div :class="`c-img ${isImgRounded ? 'c-img--round' : ''} ${props.isSubResult ? 'c-img--s' : ''}`">
        <a
            v-if="props.result.type === ResultTypes.BADGE"
            href="#open-badge-modal"
            @click.prevent="emit('badgeSelected', props.result)"
        >
            <sp-results-result-image-wrapper :result="props.result" />
        </a>
        <a v-else-if="props.result.url" :href="props.result.url">
            <sp-results-result-image-wrapper :result="props.result" />
        </a>
        <div v-else>
            <sp-results-result-image-wrapper :result="props.result" />
        </div>
    </div>
</template>

<style lang="scss">
@import "@css/core/base/icon-colours";
</style>
