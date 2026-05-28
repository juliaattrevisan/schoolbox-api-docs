<script setup lang="ts" generic="TCurriculumNode extends Pick<Node, 'id' | 'code'>">
/**
 * Used to display an inline list of curriculum codes that can be selected
 * and emit an event when a node is selected
 */
import type { Node } from "@schoolboxCurriculum/types/Node";

interface Props {
    /**
     * the nodes containing the codes to display
     */
    nodes: TCurriculumNode[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
    selected: [value: TCurriculumNode];
}>();

/**
 * dispatch an emit to notify that a node has been selected
 * @param node - the selected node
 */
function onCodeSelected(node: TCurriculumNode): void {
    emit("selected", node);
}
</script>

<template>
    <div class="l-flex-row l-flex--0-shrink l-gap-xs">
        <template v-for="(node, index) in props.nodes" :key="node.id">
            <button class="meta-button" title="View details" @click.prevent="onCodeSelected(node)">
                {{ node.code }}<template v-if="index + 1 < props.nodes.length">, </template>
            </button>
        </template>
    </div>
</template>
