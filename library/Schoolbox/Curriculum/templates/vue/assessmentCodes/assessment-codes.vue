<script setup lang="ts">
/**
 * A container to display curriculum codes linked to an assessment
 * It's specific to an assesment as the other areas that display curriculum codes are
 * currently handled in other Vue files
 */

import CodesList from "@schoolboxCurriculum/vue/codes-list.vue";
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import NodeDetails from "@schoolboxCurriculum/vue/node-details.vue";
import type { Node } from "@schoolboxCurriculum/types/Node";

interface Props {
    /**
     * the nodes to load and display
     */
    nodes: Node[];
}

const props = defineProps<Props>();

const selectedNodeId = window.Vue.ref<number | null>(null);
const modalEl = window.Vue.ref<InstanceType<typeof RevealModal> | null>(null);

/**
 * to open up the details modal
 * @param { Node } node - the selected node
 */
function onCodeSelected(node: Node) {
    selectedNodeId.value = node.id;

    if (modalEl.value !== null) {
        modalEl.value.open();
    }
}
</script>

<template>
    <codes-list :nodes="props.nodes" @selected="onCodeSelected" />
    <reveal-modal ref="modalEl">
        <node-details v-if="selectedNodeId !== null" :node-id="selectedNodeId" />
    </reveal-modal>
</template>
