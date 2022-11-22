<script setup>

/**
 * To be able to enable/disable or delete a node
 */

import { computed } from 'vue';
import { useSelectedNodesStore } from '../selectedNodes/store/mcSelectedNodesStore';


const props = defineProps({
    /**
     * the assigned node to display
     */
    node: {
        type: Object,
        required: true,
        default() {
            return {
                id: '0',
                name: '-',
                children: [],
            }
        }
    },
    /**
     * to disable certain events when any editing is taking place
     */
    isUpdatingOrAdding: {
        type: Boolean,
        required: true,
    },
});

const selectedNodesStore = useSelectedNodesStore();

/**
 * if the node is a child of one that's selected it should be disabled
 *
 * @returns { Boolean }
 */
const isDisabled = computed(() => {
    return selectedNodesStore.childNodesIds.includes(props.node.id) || props.isUpdatingOrAdding;
});

/**
 * Update the status of the checkbox based on whether it's selected or not
 *
 * @returns { Boolean }
 */
const isChecked = computed(() => {
    return selectedNodesStore.nodesIds.includes(props.node.id) || selectedNodesStore.childNodesIds.includes(props.node.id);
});

/**
 * add or remove based on the current status
 *
 * @param { boolean }
 */
function onChange (isChecked) {
    selectedNodesStore.switchNode(props.node, isChecked);
}

</script>

<template>
    <div class="checklist">
        <input
            :id="`checkbox-${props.node.id}`"
            type="checkbox"
            :value="props.node.id"
            :disabled="isDisabled"
            :checked="isChecked"
            @change="onChange($event.target.checked)"
        />
        <label :for="`checkbox-${props.node.id}`"></label>
    </div>
</template>

<style lang="scss" scoped>

.checklist {
    flex: 2rem 0 0; //ensure a long name doesn't impact the checkbox width, which may add dodgy scroll bars on it
    padding-top: 0.5rem;
}

</style>