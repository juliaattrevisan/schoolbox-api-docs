<script setup lang="ts">
/**
 * To display a nodes description
 */

import MediaArticle from "@schoolboxVue/components/media-article.vue";
import type { Node } from "@schoolboxCurriculum/types/Node";

interface Props {
    node: Node; // the assigned node to display
}
const props = defineProps<Props>();
</script>

<template>
    <media-article tag="div" :content="props.node.description || ''"></media-article>
</template>

<style scoped></style>
