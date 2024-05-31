<script setup lang="ts">
/**
 * The component-action for a textbox cell on the unit planner
 * Handles the creation, modification, and saving of the textbox content
 */
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import { useUnitPlannerTextboxStore } from "./store/unitPlannerTextboxStore";
import { Column, ColumnType } from "./unitPlannerConfig";
import { Unit } from "./unitPlannerTypes";

interface Props {
    unit: Unit;
    column: Column;
}

const props = defineProps<Props>();
const emit = defineEmits(["modifyClicked"]);
const store = useUnitPlannerStore();
const textboxStore = useUnitPlannerTextboxStore();

// Whether the textbox exists in the backend, or if it still needs to be created
const textboxIsCreated = window.Vue.computed(() => {
    return (store.units.find((unit) => unit.id === props.unit.id)?.textboxes[props.column.label].id || 0) >= 0;
});

const contentIsFocused = window.Vue.computed(() => {
    return (store.focusedUnitCells[props.unit.id] ?? []).includes(props.column.id);
});

const contentIsSaved = window.Vue.computed(() => {
    return textboxStore.unsavedTextboxes[props.unit.id]?.[props.column.id] === undefined;
});

const isCreatingTextbox = window.Vue.ref(false);
const isContentSaving = window.Vue.ref(false);

/**
 * When the modify button is clicked.
 */
function onModifyClicked() {
    // Let the parent component know that the modify button was clicked,
    // so that it can handle the action. E.g. focus the ckeditor
    emit("modifyClicked");
}

/**
 * When the create button is clicked.
 */
async function onCreateClicked() {
    isCreatingTextbox.value = true;
    await textboxStore.createTextbox(props.unit.id, props.column);
    await window.Vue.nextTick();
    isCreatingTextbox.value = false;
    onModifyClicked();
}

// Allow the parent component to create the textbox
defineExpose({ onCreateClicked });

/**
 * When the save button is clicked.
 */
function onSaveClicked() {
    if (props.column.type !== ColumnType.OVERVIEW) return; // Only applicable to the overview column
    isContentSaving.value = true;

    textboxStore
        .saveTextboxContent(props.unit.id, props.column)
        .catch((err) => {
            throw err;
        })
        .finally(() => {
            isContentSaving.value = false;
        });
}
</script>

<template>
    <div class="component-action">
        <section>
            <button v-if="!contentIsSaved && !isContentSaving" data-role="dirty-indicator" disabled>
                Changes not saved
            </button>

            <button v-if="isContentSaving" class="button">Saving...</button>
            <button v-else-if="isCreatingTextbox" class="button">Creating...</button>
            <button
                v-else-if="!textboxIsCreated"
                class="button"
                :data-test="`unit-planner-textbox-action-create-${props.unit.id}-${props.column.id}`"
                @click="onCreateClicked"
            >
                Create Text box
            </button>
            <button
                v-else-if="contentIsSaved && !contentIsFocused"
                class="button"
                :data-test="`unit-planner-textbox-action-modify-${props.unit.id}-${props.column.id}`"
                @click="onModifyClicked"
            >
                Modify
            </button>
            <!-- Use mouse down as clicking this will remove focus from the content, and cause it to collapse. -->
            <button
                v-else
                class="button"
                :data-test="`unit-planner-textbox-action-save-${props.unit.id}-${props.column.id}`"
                @mousedown="onSaveClicked"
            >
                Save
            </button>
        </section>
    </div>
</template>

<style scoped>
.component-action section {
    border-bottom: 0;
    pointer-events: all;
}
</style>
