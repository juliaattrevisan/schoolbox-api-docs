<script setup>

/**
 * To cancel or confirm the current node selection
 */

import { computed } from 'vue';
import { useModalStore } from '../store/csModalStore';
import { useSelectedNodesStore } from '../store/csSelectedNodesStore';
import { useModalSelectedNodesStore } from '../store/csModalSelectedNodesStore';


const modalStore = useModalStore();
const selectedNodesStore = useSelectedNodesStore();
const modalSelectedNodesStore = useModalSelectedNodesStore();

/**
 * Close the modal
 */
function onCancelled () {
    if(!isSaveDisabled) {
        modalStore.cancel();
    }
}

/**
 * The selection has been confirmed, so close the modal
 * and insert into the current nodes
 */
function onSelected () {
    modalStore.select();
}

/**
 * If there's currently no selected nodes, we are Selecting nodes,
 * otherwise we are Updating
 *
 * @returns { String }
 */
const saveText = computed(() => selectedNodesStore.hasNoNodes ? 'Select' : 'Update');

/**
 * If there's no nodes currently selected and we haven't selected any
 * yet, there's no need to have the save enabled
 *
 * @returns { String }
 */
const isSaveDisabled = computed(() => selectedNodesStore.hasNoNodes && modalSelectedNodesStore.hasNoNodes);

</script>

<template>
    <ul class="flex-list buttons">
        <li><div @click="onCancelled" class="button">Cancel</div></li>
        <li><div @click="onSelected" class="button submit">{{ saveText }}</div></li>
    </ul>
</template>

<style scoped>
.flex-list.buttons .button {
    display: flex;
    justify-content: center;
    margin-bottom: 0;
}
</style>
