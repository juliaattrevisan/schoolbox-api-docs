<script setup lang="ts">
/**
 * Actions for when a grid cell can be modified and saved.
 */

const props = defineProps<{
    id: number;
    headerKey: string;
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
        <section class="f-no-margins">
            <div
                v-if="props.hasChanged && !props.isSaving"
                class="meta l-pad-s"
                data-role="dirty-indicator"
                role="status"
                aria-live="polite"
            >
                Changes not saved
            </div>
            <div v-if="props.isSaving" class="meta l-pad-s" role="status" aria-live="polite">Saving...</div>
            <button
                v-else-if="!props.hasChanged && !props.isFocused"
                class="button"
                :data-test="`lg-cell-action-save-modify-${props.id}-${props.headerKey}`"
                @click="onModifyClicked"
            >
                Modify
            </button>
            <!-- Use mouse down as clicking this will remove focus from the content, and cause it to collapse. -->
            <button
                v-else
                class="button"
                :data-test="`lg-cell-action-save-save-${props.id}-${props.headerKey}`"
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
