<script setup lang="ts">
/**
 * To display a nodes description
 */

import { ref, nextTick, onMounted } from "vue";

interface Props {
    node: Node; // the assigned node to display
}
const props = defineProps<Props>();

const descriptionEl = ref<HTMLDivElement | null>(null);

/**
 * Check if the decription contains any videos that
 * we need to apply the media player to
 */
onMounted(async () => {
    await nextTick();
    $.fn.schoolboxResource_ReplaceAll($(descriptionEl.value));
    await nextTick();
    $.fn.schoolboxResource_InitialiseMediaElementPlayer($(descriptionEl.value));
});
</script>

<template>
    <div ref="descriptionEl" v-html="props.node.description"></div>
</template>

<style scoped></style>
