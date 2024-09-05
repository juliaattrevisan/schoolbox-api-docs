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

const contentIsFocused = window.Vue.computed(() => {
    return (store.focusedUnitCells[props.unit.id] ?? []).includes(props.column.id);
});

const contentIsSaved = window.Vue.computed(() => {
    return textboxStore.unsavedTextboxes[props.unit.id]?.[props.column.id] === undefined;
});

const isCreatingTextbox = window.Vue.ref(false);
const isContentSaving = window.Vue.ref(false);

const storeUnit = window.Vue.computed(() => {
    return store.units.find((unit) => unit.id === props.unit.id);
});

const storeTextbox = window.Vue.computed(() => {
    if (storeUnit.value === undefined) return undefined;
    return storeUnit.value.textboxes.items[props.column.label];
});

/**
 * Condition for displaying the action bar:
 * - If the textbox exists in the backend:
 *   allow if the create component and textbox routes are set
 * - Otherwise:
 *   allow if the textbox's modify route is set
 */
const displayActionBar = window.Vue.computed(() => {
    if (storeUnit.value === undefined) return false;
    if (!textboxStore.textboxIsCreated(props.unit.id, props.column)) {
        return storeUnit.value.textboxes._routes.createComponent && storeUnit.value.textboxes._routes.createTextbox;
    }

    if (storeTextbox.value === undefined) return false;
    return storeTextbox.value._links.update !== null;
});

/**
 * When the modify button is clicked.
 */
function onModifyClicked(): void {
    // Let the parent component know that the modify button was clicked,
    // so that it can handle the action. E.g. focus the ckeditor
    emit("modifyClicked");
}

/**
 * When the create button is clicked.
 */
async function onCreateClicked(): Promise<void> {
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
function onSaveClicked(): void {
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
    <div v-if="displayActionBar" class="component-action">
        <section>
            <button v-if="!contentIsSaved && !isContentSaving" data-role="dirty-indicator" disabled>
                Changes not saved
            </button>

            <button v-if="isContentSaving" class="button">Saving...</button>
            <button v-else-if="isCreatingTextbox" class="button">Creating...</button>
            <button
                v-else-if="!textboxStore.textboxIsCreated(props.unit.id, props.column)"
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
