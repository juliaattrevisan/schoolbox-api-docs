<script setup>

/**
 * A curriculum node to select to traverse further down the tree,
 * or to CRUD via the actions
 */

import { computed } from 'vue';
import { UNSET_ID } from '../store/mcNodesStore';
import { useSelectedNodesStore } from '../selectedNodes/store/mcSelectedNodesStore';

const selectedNodesStore = useSelectedNodesStore();


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

/**
 * Dont add a checklist on the very first level node
 *
 * @returns { Boolean }
 */
const isIdSet = computed(() => props.node.id !== UNSET_ID);

/**
 * Update the status of the checkbox based on whether it's selected or not
 *
 * @returns { Boolean }
 */
const isChecked = computed(() => {
    return selectedNodesStore.nodesIds.includes(props.node.id);
});

/**
 * add or remove based on the current status
 *
 * @param { boolean }
 */
function onChange (isChecked) {
    selectedNodesStore.switch(props.node, isChecked);
}

</script>

<template>
    <div v-if="isIdSet" class="checklist">
        <input
            :id="`checkbox-${props.node.id}`"
            type="checkbox"
            :value="props.node.id"
            :checked="isChecked"
            :disabled="props.isUpdatingOrAdding"
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