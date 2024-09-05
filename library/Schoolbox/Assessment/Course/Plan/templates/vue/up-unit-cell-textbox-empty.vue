<script setup lang="ts">
/**
 * The empty state for a textbox cell on the unit planner
 * Shows when:
 * - The textbox is not created yet
 * - The textbox is created, but has no content
 */
import type { Unit } from "./unitPlannerTypes";
import type { Column } from "./unitPlannerConfig";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import { useUnitPlannerTextboxStore } from "./store/unitPlannerTextboxStore";

interface Props {
    unit: Unit;
    column: Column;
}
const props = defineProps<Props>();

const store = useUnitPlannerStore();
const textboxStore = useUnitPlannerTextboxStore();
const emit = defineEmits(["modifyClicked", "createClicked"]);

const emptyStateTemplateMessage = window.Vue.computed(() => {
    const unitTemplateTextbox = store.template?.textboxes.find((textbox) => textbox.heading === props.column.label);
    return unitTemplateTextbox?.emptyStateMessage;
});

/**
 * When the modify button is clicked.
 */
function onModifyClicked(): void {
    // Let the parent component know that the modify button was clicked,
    // so that it can handle the action. E.g. focus the ckeditor

    // If the textbox was not created yet, send createClicked instead
    if (!textboxStore.textboxIsCreated(props.unit.id, props.column)) {
        emit("createClicked");
        return;
    }

    emit("modifyClicked");
}
</script>

<template>
    <i class="icon-textbox"></i>
    <p v-if="emptyStateTemplateMessage">{{ emptyStateTemplateMessage }}</p>
    <p v-if="props.unit.textboxes._routes.createComponent && props.unit.textboxes._routes.createTextbox">
        <a @click.prevent="onModifyClicked">Add content to this text box</a>
    </p>
</template>

<style scoped></style>
