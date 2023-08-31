<script setup lang="ts">
/**
 * To display the search results image or icon
 */

import { Result } from "../../js/types/Result";
import { ResultTypes } from "../../js/types/ResultTypes";

interface Props {
    result: Result; // the result to display
}
const props = defineProps<Props>();

/**
 * Whether to use a rounded image
 */
const isImgRounded = window.Vue.computed(() => {
    if (props.result.type.includes(ResultTypes.USER)) {
        return true;
    }
    return false;
});
</script>

<template>
    <div :class="`c-img ${isImgRounded ? 'c-img--round' : ''}`">
        <div class="c-img__wrapper">
            <img
                v-if="props.result.image"
                :src="(props.result.image as string)"
                :alt="`Photo of ${props.result.title}`"
            />
            <div v-else-if="props.result.icon" :class="`icon icon-${props.result.icon}`"></div>
        </div>
    </div>
</template>

<style scoped></style>
