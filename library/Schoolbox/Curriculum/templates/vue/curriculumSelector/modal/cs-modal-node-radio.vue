<script setup lang="ts">
/**
 * to select a single node within the current modal session
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import { getEmptyNode, UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import { generateUUID } from "@js/util/jsUtils";
import { useModalSelectedNodesStore } from "../store/csModalSelectedNodesStore";

const modalSelectedNodesStore = useModalSelectedNodesStore();
const extraId = generateUUID(); // to ensure the input/label id is unique

interface Props {
    node: Node; // to prefill the input
}
const props = withDefaults(defineProps<Props>(), {
    node: () => getEmptyNode(),
});

/**
 * Only show the checkbox if the node has an id
 * @returns {boolean} - whether the radio is visible or not
 */
const isVisible = window.Vue.computed(() => props.node.id !== UNSET_ID);

/**
 * Update the status of the radio based on whether it's selected or not
 * @returns {boolean} - whether the radio is checked or not
 */
const isChecked = window.Vue.computed(() => modalSelectedNodesStore.isSelected(props.node));

/**
 * add a single node to the selected nodes store
 */
function onChange(): void {
    modalSelectedNodesStore.addSingle(props.node);
}
</script>

<template>
    <div v-if="isVisible" class="checklist">
        <input
            :id="`radio-${props.node.id}__${extraId}`"
            type="radio"
            :value="props.node.id"
            :checked="isChecked"
            @change="onChange"
        />
        <label :for="`radio-${props.node.id}__${extraId}`"></label>
    </div>
</template>

<style lang="scss" scoped>
.checklist {
    flex: 2rem 0 0; // ensure a long name doesn't impact the checkbox width, which may add dodgy scroll bars on it
    padding-top: 0.5rem;
}
</style>
