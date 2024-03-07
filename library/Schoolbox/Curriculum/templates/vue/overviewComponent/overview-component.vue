<script setup lang="ts">
/**
 * The curriculum overview component that can be added to a home page
 * to display all or a single curriculum node (and all its children)
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import CurriculumDetailsModal from "@schoolboxCurriculum/vue/curriculumDetailsModal/curriculum-details-modal.vue";

interface Props {
    nodes: Node[]; // The curriculum nodes to display
}
const props = defineProps<Props>();
</script>

<template>
    <curriculum-details-modal />
</template>
