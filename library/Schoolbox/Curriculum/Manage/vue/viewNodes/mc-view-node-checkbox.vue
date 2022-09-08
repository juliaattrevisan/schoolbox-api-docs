<script setup>

/**
 * A curriculum node to select to traverse further down the tree,
 * or to CRUD via the actions
 */

import { computed } from 'vue';
import { UNSET_ID } from '../store/mcNodesStore';


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

</script>

<template>
    <div v-if="isIdSet" class="checklist">
        <input
            :id="`checkbox-${props.node.id}`"
            type="checkbox"
            :value="props.node.id"
            :checked="false"
            :disabled="props.isUpdatingOrAdding"
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