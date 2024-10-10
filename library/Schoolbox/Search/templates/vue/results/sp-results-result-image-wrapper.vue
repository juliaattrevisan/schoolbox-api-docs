<script setup lang="ts">
/**
 * To display the search results image or icon
 */

import type { Result } from "../../js/types/Result";

interface Props {
    result: Result; // the result to display
}
const props = defineProps<Props>();
</script>

<template>
    <div class="c-img__wrapper">
        <img
            v-if="props.result.image"
            :src="props.result.image"
            :alt="`Photo of ${props.result.title}`"
            :title="props.result.typeTitle"
        />
        <div v-else :class="`icon icon-${props.result.icon as string}`" :title="props.result.typeTitle"></div>
    </div>
</template>

<style scoped></style>
