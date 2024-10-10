<script setup lang="ts">
/**
 * An individual selected field in the profile editor
 */

import { animateRotation } from "@js/util/animateUtils";
import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import { useProfileEditorStore } from "./store/profileEditorStore";
import { useProfileEditorFieldsStore } from "./store/profileEditorFieldsStore";
import PeFieldsSelectedItemEdit from "./pe-fields-selected-item-edit.vue";
import PeFieldsSelectedItemRemove from "./pe-fields-selected-item-remove.vue";
import PeFieldsSelectedItemError from "./pe-fields-selected-item-error.vue";
import PeFieldsIcon from "./pe-fields-icon.vue";
import type { ProfileEditorAvailableField, ProfileEditorSelectedField } from "./types/profileEditorTypes";

interface Props {
    // Allow an available field to be passed in as well so we can use this component to also render the drag preview
    field: ProfileEditorSelectedField | ProfileEditorAvailableField;
    dragging: boolean;
}

const props = defineProps<Props>();
const peStore = useProfileEditorStore();
const peFieldsStore = useProfileEditorFieldsStore();

const editBtn = window.Vue.ref<HTMLElement | null>(null);
const removeBtn = window.Vue.ref<HTMLElement | null>(null);

const isEditPanelOpen = window.Vue.ref<boolean>(false);
const isRemovePanelOpen = window.Vue.ref<boolean>(false);

window.Vue.watch(isEditPanelOpen, (isOpen) => {
    // Animate the edit button
    if (editBtn.value) {
        const ANIMATE_CLOSED_POSITION = 0;
        const ANIMATE_OPEN_POSITION = 90;
        const ANIMATE_ORIGIN = "20px 20px";
        const ANIMATE_SPEED = 0.3;
        animateRotation(
            editBtn.value,
            isOpen ? ANIMATE_OPEN_POSITION : ANIMATE_CLOSED_POSITION,
            ANIMATE_ORIGIN,
            ANIMATE_SPEED,
        );
    }

    // Close the remove panel if the edit panel is opened
    if (isOpen) isRemovePanelOpen.value = false;
});

window.Vue.watch(isRemovePanelOpen, (isOpen) => {
    // Animate the remove button
    if (removeBtn.value) {
        const ANIMATE_CLOSED_POSITION = 0;
        const ANIMATE_OPEN_POSITION = 180;
        const ANIMATE_ORIGIN = "20px 20px";
        const ANIMATE_SPEED = 0.3;
        animateRotation(
            removeBtn.value,
            isOpen ? ANIMATE_OPEN_POSITION : ANIMATE_CLOSED_POSITION,
            ANIMATE_ORIGIN,
            ANIMATE_SPEED,
        );
    }

    // Close the edit panel if the remove panel is opened
    if (isOpen) isEditPanelOpen.value = false;
});

/**
 * Determine if the current field we are rendering is a "selected" field rather than an "available" field
 * These both have different data structures, but it is useful to use the same component to render both
 * The field will only be "available" if it is the preview of a field being dragged
 * This function also tells TypeScript to narrow the type of the field
 * @param field - the field to check
 * @returns whether the field is a selected field
 */
function isSelectedField(
    field: ProfileEditorAvailableField | ProfileEditorSelectedField,
): field is ProfileEditorSelectedField {
    return "fieldSettings" in field;
}

/**
 * Whether the FIELD itself is saved, does not check if the field settings are saved
 * That is, this is true if and only if the field exists on the server already
 */
const isFieldOnServer = window.Vue.computed(() => {
    return peStore.savedSelectedRoleType?.fields.selected.some((field) => field.field === props.field.field) === true;
});

/**
 * Whether the panel components should be available or not
 */
const showPanels = window.Vue.computed(() => {
    // If this field is not a selected field (e.g. if its a preview of a field being dragged) don't show the panels
    if (!isSelectedField(props.field)) return false;

    // If this field is not known to the server yet (we are still saving its initial add) don't show the panels
    if (!isFieldOnServer.value) return false;

    return true;
});

/**
 * The error message to display if there was a problem saving this field
 */
const fieldSaveError = window.Vue.computed(() => {
    return peFieldsStore.actionErrors[props.field.field];
});

/**
 * Whether the current field is in a "saving" state
 * This is used to disable the grip handle and show a loading spinner instead
 * to ensure the user can't interact with the field while it is saving or loading
 * This is only relevant for selected fields
 * @param field - the field to check
 * @returns whether the field is in a saving state
 */
const isSaving = window.Vue.computed(() => {
    // If there was an error, we are not saving
    if (fieldSaveError.value) return false;

    // Always show the grip for available fields, the user won't be able to interact with them
    // but this makes the drag preview look more like an actual saved field rather than having a spinner
    if (!isSelectedField(props.field)) return false;

    // Always show the loading state if the field is not on the server yet
    if (!isFieldOnServer.value) return true;

    // Finally, determine the loading state based on if this field is currently in the action queue or not
    return peFieldsStore.actionQueue.some((action) => {
        if ("field" in action && action.field === props.field.field) return true;
        if ("fields" in action && action.fields.includes(props.field.field)) return true;
        return false;
    });
});
</script>

<template>
    <div class="component l-mar-m--bottom" :class="{ saving: isSaving }" :data-sortable-item="props.field.field">
        <div class="row collapse">
            <div class="small-12 columns">
                <div class="component-titlebar">
                    <div class="actions-small-2">
                        <div class="list-item">
                            <h2 :title="props.field.displayAs">
                                <span v-if="isSaving" class="loader" />
                                <a
                                    v-else
                                    class="icon-grip"
                                    data-pe-field-drag-handle
                                    style="user-select: none; touch-action: none; cursor: grab"
                                />
                                <pe-fields-icon :field="props.field" />
                                {{ props.field.displayAs }}
                            </h2>
                        </div>
                        <nav>
                            <a
                                ref="editBtn"
                                class="icon-settings"
                                title="Settings"
                                :disabled="showPanels ? undefined : true"
                                @click="isEditPanelOpen = !isEditPanelOpen"
                            />
                            <a
                                ref="removeBtn"
                                class="icon-close"
                                title="Close"
                                :disabled="showPanels ? undefined : true"
                                @click="isRemovePanelOpen = !isRemovePanelOpen"
                            />
                        </nav>
                    </div>
                </div>

                <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
                    <div v-if="fieldSaveError" class="component-panel">
                        <pe-fields-selected-item-error :message="fieldSaveError" />
                    </div>
                </Transition>

                <div v-if="isSelectedField(props.field) && showPanels" class="component-panel">
                    <pe-fields-selected-item-edit
                        :field="props.field"
                        :is-open="isEditPanelOpen && !$props.dragging"
                        @close="isEditPanelOpen = false"
                    />
                    <pe-fields-selected-item-remove
                        :field="props.field"
                        :is-open="isRemovePanelOpen && !$props.dragging"
                        @close="isRemovePanelOpen = false"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* To remove the hover background since they rotate */
nav > a[class^="icon-"]:hover {
    background-color: transparent;
}

/*
 * Hide any component panel when this is the item held by the mouse.
 * This must be a style since the element handling the drag will not react to changes here
 */
.dragged-item .component-panel {
    display: none;
}

.loader {
    margin-left: 0px; /* Remove left margin from the loader's default styling so it aligns */
}

.icon-grip {
    display: inline-block; /* Like homepages, use inline-block so it gets a bit taller and more targetable */
}
</style>
