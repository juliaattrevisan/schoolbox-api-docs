<script setup lang="ts">
/**
 * To cancel or confirm the current node selection
 */

import { useModalStore } from "./store/coseModalStore";
// import { useSelectedNodesStore } from "../store/csSelectedNodesStore";
// import { useModalSelectedNodesStore } from "../store/csModalSelectedNodesStore";

const modalStore = useModalStore();
// const selectedNodesStore = useSelectedNodesStore();
// const modalSelectedNodesStore = useModalSelectedNodesStore();

/**
 * Close the modal
 */
function onCancelled(): void {
    modalStore.close();
}

/**
 * The selection has been confirmed, so close the modal
 * and insert into the current selection
 */
function onSelected(): void {
    modalStore.select();
}

/**
 * If there's currently a selection,
 * otherwise we are Modifying
 * @returns - the text to display on the button
 */
// const saveText = window.Vue.computed(() => (selectedNodesStore.hasNoNodes ? "Select" : "Modify"));
const saveText = window.Vue.computed(() => "Select");
</script>

<template>
    <ul class="flex-list buttons">
        <li><button type="button" class="button" :disabled="false" @click.prevent="onCancelled">Cancel</button></li>
        <li>
            <button type="button" class="button submit" @click.prevent="onSelected">
                {{ saveText }}
            </button>
        </li>
    </ul>
</template>

<style scoped></style>
