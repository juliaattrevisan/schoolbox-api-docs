<script setup>
/**
 * displays further info in a modal on a curriculm node that's been selected
 */

import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import NodeDetails from "@schoolboxCurriculum/vue/node-details";
import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";

const props = defineProps({
    /**
     * the selected curriculum node
     */
    node: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(["closed"]);
const modalEl = window.Vue.ref(null);

/**
 * to open the modal when requested
 */
window.Vue.watch(
    () => props.node,
    (newNode) => {
        if (newNode.id !== UNSET_ID) {
            modalEl.value.open();
        }
    }
);

/**
 * close the modal, setting the activeNode to null so we can allow another selection
 */
function onModalClosed() {
    emit("closed");
}
</script>

<template>
    <reveal-modal ref="modalEl" size="large" @closed="onModalClosed">
        <node-details :node="props.node" />
    </reveal-modal>
</template>
