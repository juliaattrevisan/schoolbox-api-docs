<script setup lang="ts">
/**
 * To cancel or confirm the current node selection
 */

import { useModalStore } from "../store/csModalStore";
import { useSelectedNodesStore } from "../store/csSelectedNodesStore";
import { useModalSelectedNodesStore } from "../store/csModalSelectedNodesStore";

const modalStore = useModalStore();
const selectedNodesStore = useSelectedNodesStore();
const modalSelectedNodesStore = useModalSelectedNodesStore();

/**
 * Close the modal
 */
function onCancelled(): void {
    modalStore.cancel();
}

/**
 * The selection has been confirmed, so close the modal
 * and insert into the current nodes
 */
function onSelected(): void {
    modalStore.select();
}

/**
 * If there's currently no selected nodes, we are Selecting nodes,
 * otherwise we are Updating
 * @returns {string} - the text to display on the button
 */
const saveText = window.Vue.computed(() => (selectedNodesStore.hasNoNodes ? "Select" : "Modify"));

/**
 * If there's no nodes currently selected and we haven't selected any
 * yet, there's no need to have the save enabled
 * @returns {string} - whether the button is disabled or not
 */
const isSaveDisabled = window.Vue.computed(() => selectedNodesStore.hasNoNodes && modalSelectedNodesStore.hasNoNodes);
</script>

<template>
    <ul class="flex-list buttons">
        <li><button type="button" class="button" :disabled="false" @click.prevent="onCancelled">Cancel</button></li>
        <li>
            <button type="button" class="button submit" :disabled="isSaveDisabled" @click.prevent="onSelected">
                {{ saveText }}
            </button>
        </li>
    </ul>
</template>

<style scoped></style>
