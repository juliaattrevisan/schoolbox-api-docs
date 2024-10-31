<script setup lang="ts">
/**
 * Display the modal buttons
 */
const emit = defineEmits<{
    cancel: [];
    select: [];
}>();

/**
 * To prompt the modal close
 */
function onCancel(): void {
    emit("cancel");
}

/**
 * To prompt the modal select
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
