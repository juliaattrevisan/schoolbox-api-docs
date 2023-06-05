<script setup>
/**
 * A container to display curriculum codes linked to an assessment
 * It's specific to an assesment as the other areas that display curriculum codes are
 * currently handled in other Vue files
 */

import CodesList from "@schoolboxCurriculum/vue/codes-list";
import RevealModal from "@schoolboxVue/components/reveal-modal";
import NodeDetails from "@schoolboxCurriculum/vue/node-details";
import { getEmptyNode } from "@schoolboxCurriculum/js/nodeUtils";

const props = defineProps({
    /**
     * the nodes to load and display
     */
    nodes: {
        type: Array,
        required: true,
    },
});

const selectedNode = window.Vue.ref(getEmptyNode());
const modalEl = window.Vue.ref(null);

/**
 * to open up the details modal
 *
 * @pararm { object }
 */
function onCodeSelected(node) {
    selectedNode.value = node;
    modalEl.value.open();
}
</script>

<template>
    <codes-list :nodes="props.nodes" @selected="onCodeSelected" />
    <reveal-modal ref="modalEl">
        <node-details :node="selectedNode" />
    </reveal-modal>
</template>
