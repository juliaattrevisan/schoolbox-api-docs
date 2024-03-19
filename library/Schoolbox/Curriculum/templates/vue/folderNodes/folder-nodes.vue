<script setup lang="ts">
/**
 * Displays the selected curriculum nodes assigned to a folder
 * The selected nodes is displayed in a tree structure, showing both the ancestors and descendants
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import FnNode from "./fn-node.vue";

interface Props {
    nodeTrees: Node[]; // The curriculum nodes trees to display
}
const props = defineProps<Props>();

const depth = window.Vue.ref(0); // to increment each time we go down the node tree to visually indicate children
</script>

<template>
    <div class="l-flex-col l-pad-s--horiz l-pad-s--top">
        <fn-node v-for="node in props.nodeTrees" :key="node.id" :node="node" :depth="depth" />
    </div>
</template>

<style scoped></style>
