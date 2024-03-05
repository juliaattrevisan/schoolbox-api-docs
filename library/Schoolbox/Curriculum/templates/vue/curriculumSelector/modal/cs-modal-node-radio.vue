<script setup lang="ts">
/**
 * to select a single node within the current modal session
 */

import type { Node } from "@schoolboxCurriculum/js/nodeUtils";
import { generateUUID } from "@js/util/jsUtils";
import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import { useModalSelectedNodesStore } from "../store/csModalSelectedNodesStore";

const modalSelectedNodesStore = useModalSelectedNodesStore();
const extraId = generateUUID(); // to ensure the input/label id is unique

interface Props {
    node: Node[]; // to prefill the input
}
const props = withDefaults(defineProps<Props>(), {
    node: () => {
        return {
            id: UNSET_ID,
            name: "-",
            children: [],
        };
    },
});

/**
 * Update the status of the checkbox based on whether it's selected or not
 * @returns {boolean} - whether the checkbox is checked or not
 */
const isChecked = window.Vue.computed(() => modalSelectedNodesStore.isSelected(props.node));

/**
 * add or remove based on the current status
 * @param {boolean} $isChecked - whether the checkbox is checked or not
 */
function onChange($isChecked: boolean) {
    modalSelectedNodesStore.switch(props.node, $isChecked);
}
</script>

<template>
    <div class="checklist">
        <input
            :id="`radio-${props.node.id}__${extraId}`"
            type="radio"
            :value="props.node.id"
            :checked="isChecked"
            @change="onChange($event.target.checked)"
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
