<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
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
function onCancelled() {
    modalStore.cancel();
}

/**
 * The selection has been confirmed, so close the modal
 * and insert into the current nodes
 */
function onSelected() {
    modalStore.select();
}

/**
 * If there's currently no selected nodes, we are Selecting nodes,
 * otherwise we are Updating
 *
 * @returns { String }
 */
const saveText = window.Vue.computed(() => (selectedNodesStore.hasNoNodes ? "Select" : "Modify"));

/**
 * If there's no nodes currently selected and we haven't selected any
 * yet, there's no need to have the save enabled
 *
 * @returns { String }
 */
const isSaveDisabled = window.Vue.computed(() => selectedNodesStore.hasNoNodes && modalSelectedNodesStore.hasNoNodes);
</script>

<template>
    <ul class="flex-list buttons">
        <li><button type="button" @click.prevent="onCancelled" class="button" :disabled="false">Cancel</button></li>
        <li>
            <button type="button" @click.prevent="onSelected" class="button submit" :disabled="isSaveDisabled">
                {{ saveText }}
            </button>
        </li>
    </ul>
</template>

<style scoped></style>
