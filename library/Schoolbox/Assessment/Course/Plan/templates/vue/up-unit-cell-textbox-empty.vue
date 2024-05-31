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

interface Props {
    unit: Unit;
    column: Column;
}
const props = defineProps<Props>();

const store = useUnitPlannerStore();
const emit = defineEmits(["modifyClicked", "createClicked"]);

// Whether the textbox exists in the backend, or if it still needs to be created
const textboxIsCreated = window.Vue.computed(() => {
    return (store.units.find((unit) => unit.id === props.unit.id)?.textboxes[props.column.label].id || 0) >= 0;
});

const emptyStateTemplateMessage = window.Vue.computed(() => {
    const unitTemplateTextbox = store.template?.textboxes.find((textbox) => textbox.heading === props.column.label);
    return unitTemplateTextbox?.emptyStateMessage;
});

/**
 * When the modify button is clicked.
 */
function onModifyClicked() {
    // Let the parent component know that the modify button was clicked,
    // so that it can handle the action. E.g. focus the ckeditor

    // If the textbox was not created yet, send createClicked instead
    if (!textboxIsCreated.value) {
        emit("createClicked");
        return;
    }

    emit("modifyClicked");
}
</script>

<template>
    <i class="icon-textbox"></i>
    <p v-if="emptyStateTemplateMessage">{{ emptyStateTemplateMessage }}</p>
    <p>
        <a @click.prevent="onModifyClicked">Add content to this text box</a>
    </p>
</template>

<style scoped></style>
