<script setup lang="ts">
import type { Unit } from "./unitPlannerTypes";
import type { Column } from "./unitPlannerConfig";

interface Props {
    unit: Unit;
    column: Column;
}
const props = defineProps<Props>();
const emit = defineEmits(["modifyClicked"]);

/**
 * When the modify button is clicked.
 */
function onModifyClicked() {
    // Let the parent component know that the modify button was clicked,
    // so that it can handle the action. E.g. focus the ckeditor
    emit("modifyClicked");
}
</script>

<template>
    <i class="icon-textbox"></i>
    <!-- TODO: Set empty state message correctly... -->
    <p>🐍 Empty state message here 🐍</p>
    <p><a @click.prevent="onModifyClicked">Add content to this text box</a>.</p>
</template>

<style scoped></style>
