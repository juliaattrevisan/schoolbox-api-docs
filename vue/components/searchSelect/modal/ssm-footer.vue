<script setup lang="ts">
/**
 * Display the modal buttons
 */

// import { useModalStore } from "./store/coseModalStore";
// import { useSelectedNodesStore } from "../store/csSelectedNodesStore";
// import { useModalSelectedNodesStore } from "../store/csModalSelectedNodesStore";

// const modalStore = useModalStore();
// const selectedNodesStore = useSelectedNodesStore();
// const modalSelectedNodesStore = useModalSelectedNodesStore();

const emit = defineEmits<{
    cancel: [];
    select: [];
}>();

/**
 * Close the modal
 */
function onCancel(): void {
    emit("cancel");
}

/**
 * The selection has been confirmed, so close the modal
 * and insert into the current selection
 */
function onSelect(): void {
    emit("select");
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
        <li><button type="button" class="button" :disabled="false" @click.prevent="onCancel">Cancel</button></li>
        <li>
            <button type="button" class="button submit" @click.prevent="onSelect">
                {{ saveText }}
            </button>
        </li>
    </ul>
</template>

<style scoped></style>
