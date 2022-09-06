<script setup>

/**
 * A curriculum node to select to traverse further down the tree,
 * or to CRUD via the actions
 */

import { computed } from 'vue';
import { UNSET_ID } from '../store/mcNodesStore';
import { useSelectedNodesStore } from '../selectedNodes/store/mcSelectedNodesStore';
import { useSearchStore } from '../store/mcSearchStore';


const selectedNodesStore = useSelectedNodesStore();
const searchStore = useSearchStore();


const props = defineProps({
    /**
     * the assigned node to display
     */
    code: {
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
});

/**
 * Dont add a checklist on the very first level node
 *
 * @returns { Boolean }
 */
const isIdSet = computed(() => props.code.id !== UNSET_ID);

/**
 * if the node is a child of one that's selected but is not in
 * the selected list it should be disabled
 *
 * @returns { Boolean }
 */
const isDisabled = computed(() => {
    return props.code.isSelected && !selectedNodesStore.codesIds.includes(props.code.id);
});

/**
 * Update the status of the checkbox based on whether it's selected or not
 *
 * @returns { Boolean }
 */
const isChecked = computed(() => props.code.isSelected);

/**
 * add or remove based on the current status
 *
 * @param { boolean }
 */
function onChange (isChecked) {
    selectedNodesStore.switch(props.code, isChecked);
}

</script>

<template>
    <div v-if="isIdSet" class="checklist">
        <input
            :id="`checkbox-${props.code.id}`"
            type="checkbox"
            :value="props.code.id"
            :disabled="isDisabled"
            :checked="isChecked"
            @change="onChange($event.target.checked)"
        />
        <label :for="`checkbox-${props.code.id}`"></label>
    </div>
</template>

<style lang="scss" scoped>

.checklist {
    flex: 2rem 0 0; //ensure a long name doesn't impact the checkbox width, which may add dodgy scroll bars on it
    padding-top: 0.5rem;
}

</style>