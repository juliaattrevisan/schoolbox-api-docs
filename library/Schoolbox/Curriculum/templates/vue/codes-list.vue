<script setup lang="ts">
/**
 * Used to display an inline list of curriculum codes that can be selected
 * and emit an event when a node is selected
 */
import type { Node } from "@schoolboxCurriculum/types/Node";

interface Props {
    /**
     * the nodes containing the codes to display
     */
    nodes: Node[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
    selected: [value: Node];
}>();

/**
 * dispatch an emit to notify that a node has been selected
 * @param { Node } node - the selected node
 */
function onCodeSelected(node: Node) {
    emit("selected", node);
}
</script>

<template>
    <template v-for="(node, index) in props.nodes" :key="node.id">
        <button class="meta-button" title="View details" @click.prevent="onCodeSelected(node)">
            {{ node.code }}
        </button>
        <template v-if="index + 1 < props.nodes.length">, </template>
    </template>
</template>
