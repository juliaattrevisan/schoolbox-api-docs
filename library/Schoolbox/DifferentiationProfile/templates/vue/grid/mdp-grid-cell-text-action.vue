<script setup lang="ts">
/**
 * The component for the action buttons for a textbox cell
 */
import type { Profile } from "../../js/types/manageDifferentiationProfilesTypes";

const props = defineProps<{
    profile: Profile;
    headerKey: string;
    hasChanged: boolean;
}>();

// const contentIsSaved = window.Vue.ref(true);
const isContentSaving = window.Vue.ref(false);
const contentIsFocused = window.Vue.ref(false);

const emit = defineEmits(["modify", "save"]);
// const store = useUnitPlannerStore();
// const textboxStore = useUnitPlannerTextboxStore();

// const contentIsFocused = window.Vue.computed(() => {
//     return (store.focusedUnitCells[props.unit.id] ?? []).includes(props.column.id);
// });

// const contentIsSaved = window.Vue.computed(() => {
//     return textboxStore.unsavedTextboxes[props.unit.id]?.[props.column.id] === undefined;
// });

// const isContentSaving = window.Vue.ref(false);

// const storeUnit = window.Vue.computed(() => {
//     return store.units.find((unit) => unit.id === props.unit.id);
// });

// const storeTextbox = window.Vue.computed(() => {
//     if (storeUnit.value === undefined) return undefined;
//     return storeUnit.value.textboxes.items[props.column.label];
// });

/**
 * When the modify button is clicked.
 */
function onModifyClicked(): void {
    emit("modify");
}

/**
 * When the save button is clicked.
 */
function onSaveClicked(): void {
    emit("save");
}
</script>

<template>
    <div class="component-action">
        <section>
            <button v-if="!props.hasChanged && !isContentSaving" data-role="dirty-indicator" disabled>
                Changes not saved
            </button>
            <button v-if="isContentSaving" class="button">Saving...</button>
            <button
                v-else-if="props.hasChanged && !contentIsFocused"
                class="button"
                :data-test="`mdp-grid-cell-text-action-modify-${props.profile.student.id}-${props.headerKey}`"
                @click="onModifyClicked"
            >
                Modify
            </button>
            <!-- Use mouse down as clicking this will remove focus from the content, and cause it to collapse. -->
            <button
                v-else
                class="button"
                :data-test="`mdp-grid-cell-text-action-save-${props.profile.student.id}-${props.headerKey}`"
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
