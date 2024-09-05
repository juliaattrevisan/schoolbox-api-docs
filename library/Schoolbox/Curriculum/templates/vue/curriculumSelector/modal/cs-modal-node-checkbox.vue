<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * to select a node within the current modal session
 */

import { generateUUID } from "@js/util/jsUtils";
import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import { useModalSelectedNodesStore } from "../store/csModalSelectedNodesStore";

const modalSelectedNodesStore = useModalSelectedNodesStore();
const extraId = generateUUID(); //to ensure the input/label id is unique

const props = defineProps({
    /**
     * the assigned node to display
     */
    node: {
        type: Object,
        required: true,
        default() {
            return {
                id: "0",
                name: "-",
                children: [],
            };
        },
    },
});

/**
 * Dont add a checklist on the very first level node
 *
 * @returns { Boolean }
 */
const isIdSet = window.Vue.computed(() => props.node.id !== UNSET_ID);

/**
 * If there's a code available, which means it can be selected
 *
 * @returns { Boolean }
 */
const hasCode = window.Vue.computed(() => props.node.code && props.node.code !== "");

/**
 * Only show the checkbox if the node has an id and a code
 *
 * @returns { Boolean }
 */
const isVisible = window.Vue.computed(() => isIdSet.value && hasCode.value);

/**
 * Update the status of the checkbox based on whether it's selected or not
 *
 * @returns { Boolean }
 */
const isChecked = window.Vue.computed(() => modalSelectedNodesStore.isSelected(props.node));

/**
 * add or remove based on the current status
 *
 * @param { boolean }
 */
function onChange(isChecked) {
    modalSelectedNodesStore.switch(props.node, isChecked);
}
</script>

<template>
    <div v-if="isVisible" class="checklist">
        <input
            :id="`checkbox-${props.node.id}__${extraId}`"
            type="checkbox"
            :value="props.node.id"
            :checked="isChecked"
            @change="onChange($event.target.checked)"
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
