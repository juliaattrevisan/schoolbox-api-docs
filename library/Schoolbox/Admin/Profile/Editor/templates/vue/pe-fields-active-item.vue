<script setup lang="ts">
/**
 * An individual active field in the profile editor
 */

import { animateRotation } from "@js/util/animateUtils";
import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import { useProfileEditorStore } from "./store/profileEditorStore";
import { useProfileEditorFieldsStore } from "./store/profileEditorFieldsStore";
import PeFieldsActiveItemEdit from "./pe-fields-active-item-edit.vue";
import PeFieldsActiveItemRemove from "./pe-fields-active-item-remove.vue";
import PeFieldsActiveItemError from "./pe-fields-active-item-error.vue";
import PeFieldsIcon from "./pe-fields-icon.vue";
import type { ProfileEditorActiveField, ProfileEditorMandatoryField } from "./types/profileEditorTypes";

interface Props {
    // Allow an available field to be passed in as well so we can use this component to also render the drag preview
    field: ProfileEditorActiveField | ProfileEditorMandatoryField;
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
 * Determine if the current field is NOT an "available" field
 * Each field type has different properties, but it is useful to use the same component to render both
 * The field will only be "available" if it is the preview of a field being dragged
 * This allows us to narrow the type of the field so we can access other properties on it
 * @param field - the field to check
 * @returns whether the field is NOT an available field
 */
function isNotAvailableField(field: Props["field"]): field is ProfileEditorActiveField {
    return "fieldSettings" in field;
}

/**
 * Determine if the current field we are rendering is a "mandatory" field
 * @param field - the field to check
 * @returns whether the field is a mandatory field
 */
function isMandatoryField(field: Props["field"]): field is ProfileEditorMandatoryField {
    if (!isNotAvailableField(field)) return false;
    if (!peStore.selectedRoleType) return false;
    return peStore.selectedRoleType.fields.mandatory.some((mandatoryField) => mandatoryField.field === field.field);
}

/**
 * Whether the FIELD itself is saved, does not check if the field settings are saved
 * That is, this is true if and only if the field exists on the server already
 */
const isFieldOnServer = window.Vue.computed(() => {
    const activeFieldType = peFieldsStore.getActiveFieldType(props.field.field);
    if (activeFieldType === null) return false;

    return peStore.savedSelectedRoleType?.fields[activeFieldType].some((field) => field.field === props.field.field);
});

/**
 * Whether the field should be draggable or not
 * This is true if it's a selected or available field, but not if it's a mandatory field
 */
const isDraggable = window.Vue.computed(() => {
    return !isMandatoryField(props.field);
});

/**
 * The panels that should be shown for this field
 * - false: don't show the panel or button for it at all
 * - true: show the button for the panel, and allow it to be opened
 * - disabled: show the button for the panel, but don't allow it to be opened
 */
const panelsToShow = window.Vue.computed<{ edit: boolean | "disabled"; remove: boolean | "disabled" }>(() => {
    // If this field is an available field, show disabled states
    if (!isNotAvailableField(props.field)) return { edit: "disabled", remove: "disabled" };

    // If this field is not known to the server yet (we are still saving its initial add) don't show the panels
    if (!isFieldOnServer.value) return { edit: "disabled", remove: "disabled" };

    return {
        // Always show the edit panel
        edit: true,
        // Only show the remove panel if it's not a mandatory field
        remove: !isMandatoryField(props.field),
    };
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
    if (!isNotAvailableField(props.field)) return false;

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
    <div class="component l-mar-m--bottom" :data-sortable-item="isDraggable ? props.field.field : undefined">
        <div class="row collapse">
            <div class="small-12 columns">
                <div class="component-titlebar">
                    <div class="actions-small-2">
                        <div class="list-item">
                            <h2 :title="props.field.displayAs">
                                <span v-if="isSaving" class="loader" />
                                <a
                                    v-else-if="isDraggable"
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
                                v-if="panelsToShow.edit !== false"
                                ref="editBtn"
                                class="icon-settings"
                                title="Settings"
                                :disabled="panelsToShow.edit ? undefined : true"
                                @click="isEditPanelOpen = !isEditPanelOpen"
                            />
                            <a
                                v-if="panelsToShow.remove !== false"
                                ref="removeBtn"
                                class="icon-close"
                                title="Close"
                                :disabled="panelsToShow.remove ? undefined : true"
                                @click="isRemovePanelOpen = !isRemovePanelOpen"
                            />
                        </nav>
                    </div>
                </div>

                <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
                    <div v-if="fieldSaveError" class="component-panel">
                        <pe-fields-active-item-error :message="fieldSaveError" />
                    </div>
                </Transition>

                <div v-if="isNotAvailableField(props.field)" class="component-panel">
                    <pe-fields-active-item-edit
                        v-if="panelsToShow.edit !== false"
                        :field="props.field"
                        :is-open="isEditPanelOpen && !$props.dragging"
                        @close="isEditPanelOpen = false"
                    />
                    <pe-fields-active-item-remove
                        v-if="panelsToShow.remove !== false"
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
