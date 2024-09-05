<script setup lang="ts">
/**
 * displays further info in a modal on a curriculm node that's been selected
 */

import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import NodeDetails from "@schoolboxCurriculum/vue/node-details.vue";
import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import type { Node } from "@schoolboxCurriculum/types/Node";

interface Props {
    /**
     * the selected curriculum node
     */
    node: Node;
}

const props = defineProps<Props>();

const emit = defineEmits(["closed"]);
const modalEl = window.Vue.ref<InstanceType<typeof RevealModal> | null>(null);

/**
 * to open the modal when requested
 */
window.Vue.watch(
    () => props.node,
    (newNode) => {
        if (newNode.id !== UNSET_ID && modalEl.value !== null) {
            modalEl.value.open();
        }
    },
);

const selectedNodeId = window.Vue.computed(() => {
    if (props.node.id === UNSET_ID) {
        return null;
    }

    return props.node.id;
});

/**
 * close the modal, setting the activeNode to null so we can allow another selection
 */
function onModalClosed(): void {
    emit("closed");
}
</script>

<template>
    <reveal-modal ref="modalEl" size="large" @closed="onModalClosed">
        <node-details v-if="selectedNodeId !== null" :node-id="selectedNodeId" />
    </reveal-modal>
</template>
