<script setup lang="ts">
/**
 * The component for the action buttons for a textbox cell
 */
import type { Profile, ProfileTextKeys } from "../../js/types/manageDifferentiationProfilesTypes";

const props = defineProps<{
    profile: Profile;
    headerKey: ProfileTextKeys;
    hasChanged: boolean;
    isSaving: boolean;
    isFocused: boolean;
}>();

const emit = defineEmits(["modify", "save"]);

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
            <button v-if="props.hasChanged && !props.isSaving" data-role="dirty-indicator" disabled>
                Changes not saved
            </button>
            <button v-if="props.isSaving" class="button">Saving...</button>
            <button
                v-else-if="!props.hasChanged && !props.isFocused"
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
