<script setup lang="ts">
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

const storeTextbox = window.Vue.computed(() => {
    const storeUnit = store.units.find((unit) => unit.id === props.unit.id);

    if (!storeUnit) return null;
    return storeUnit.textboxes[props.column.label];
});

const contentIsSaved = window.Vue.computed(() => {
    return textboxStore.unsavedTextboxes[props.unit.id]?.[props.column.id] === undefined;
});

const isOverviewContentSaving = window.Vue.ref(false);

/**
 * When the modify button is clicked.
 */
function onModifyClicked() {
    // Let the parent component know that the modify button was clicked,
    // so that it can handle the action. E.g. focus the ckeditor
    emit("modifyClicked");
}

/**
 * When the save button is clicked.
 */
function onSaveClicked() {
    if (props.column.type !== ColumnType.OVERVIEW) return; // Only applicable to the overview column
    isOverviewContentSaving.value = true;

    textboxStore
        .saveTextboxContent(props.unit.id, props.column)
        .catch((err) => {
            throw err;
        })
        .finally(() => {
            isOverviewContentSaving.value = false;
        });
}
</script>

<template>
    <div class="component-action">
        <section>
            <button v-if="!contentIsSaved && !isOverviewContentSaving" data-role="dirty-indicator" disabled>
                Changes not saved
            </button>

            <button v-if="isOverviewContentSaving" class="button">Saving...</button>

            <button v-else-if="contentIsSaved" class="button" @click="onModifyClicked">
                {{ (storeTextbox?.id || 0) >= 0 ? "Modify" : "Create Textbox" }}
            </button>
            <!-- Use mouse down as clicking this will remove focus from the content, and cause it to collapse. -->
            <button v-else class="button" @mousedown="onSaveClicked">Save</button>
        </section>
    </div>
</template>

<style scoped lang="scss">
.component-action section {
    border-bottom: 0;
    pointer-events: all;
}
</style>
