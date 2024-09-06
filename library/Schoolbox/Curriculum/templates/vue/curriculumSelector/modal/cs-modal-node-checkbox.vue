<script setup lang="ts">
/**
 * to select a node within the current modal session
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import { getEmptyNode, UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import { generateUUID } from "@js/util/jsUtils";
import { useStore } from "../store/curriculumSelectorStore";
import { useModalSelectedNodesStore } from "../store/csModalSelectedNodesStore";

const store = useStore();
const modalSelectedNodesStore = useModalSelectedNodesStore();
const extraId = generateUUID(); // to ensure the input/label id is unique

interface Props {
    node: Node; // to prefill the input
}
const props = withDefaults(defineProps<Props>(), {
    node: () => getEmptyNode(),
});

/**
 * Don't add a checklist on the very first level node
 * @returns {boolean} - whether the node has an id or not
 */
const isIdSet = window.Vue.computed(() => props.node.id !== UNSET_ID);

/**
 * If there's a code available, which means it can be selected
 * @returns {boolean} - whether the node has a code or not
 */
const hasCode = window.Vue.computed(() => props.node.code && props.node.code !== "");

/**
 * Only show the checkbox if the node has an id and a code
 * @returns {boolean} - whether the checkbox is visible or not
 */
const isVisible = window.Vue.computed(() => (store.isSelectAll as boolean) || (isIdSet.value && hasCode.value));

/**
 * Update the status of the checkbox based on whether it's selected or not
 * @returns {boolean} - whether the checkbox is checked or not
 */
const isChecked = window.Vue.computed(() => modalSelectedNodesStore.isSelected(props.node));

/**
 * add or remove based on the current status
 * @param $isChecked - whether the checkbox is checked or not
 */
function onChange($isChecked: boolean): void {
    modalSelectedNodesStore.switch(props.node, $isChecked);
}
</script>

<template>
    <div v-if="isVisible" class="checklist">
        <input
            :id="`checkbox-${props.node.id}__${extraId}`"
            type="checkbox"
            :value="props.node.id"
            :checked="isChecked"
            @change="onChange(($event.target as HTMLInputElement).checked)"
        />
        <label :for="`checkbox-${props.node.id}__${extraId}`"></label>
    </div>
</template>

<style lang="scss" scoped>
.checklist {
    flex: 2rem 0 0; //ensure a long name doesn't impact the checkbox width, which may add dodgy scroll bars on it
    padding-top: 0.5rem;
}
</style>
