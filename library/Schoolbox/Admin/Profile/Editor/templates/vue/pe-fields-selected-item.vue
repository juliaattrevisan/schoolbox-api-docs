<script setup lang="ts">
/**
 * An individual selected field in the profile editor
 * This may be used to either render a selected field (one that can be resequenced)
 * or an available field that is currently being dragged into the selected fields list
 */

import { animateRotation } from "@js/util/animateUtils";
import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import { useProfileEditorStore } from "./store/profileEditorStore";
import { useProfileEditorFieldsStore } from "./store/profileEditorFieldsStore";
import PeFieldsActiveItemEdit from "./pe-fields-active-item-edit.vue";
import PeFieldsActiveItemRemove from "./pe-fields-active-item-remove.vue";
import PeFieldsActiveItemError from "./pe-fields-active-item-error.vue";
import PeFieldsIcon from "./pe-fields-icon.vue";
import type { ProfileEditorAvailableField, ProfileEditorSelectedField } from "./types/profileEditorTypes";

interface Props {
    // Allow an available field to be passed in as well so we can use this component to also render the drag preview
    field: ProfileEditorSelectedField | ProfileEditorAvailableField;
    // Is this field currently being dragged?
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
 * Determine if the current field is an "available" field
 * Each field type has different properties, but it is useful to use the same component to render both
 * The field will only be "available" if it is the preview of a field being dragged
 * This allows us to narrow the type of the field so we can access other properties on it
 * @param field - the field to check
 * @returns whether the field is an available field
 */
function isAvailableField(field: Props["field"]): field is ProfileEditorAvailableField {
    return !("fieldSettings" in field);
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
    if (isAvailableField(props.field)) return false;

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
    <li :data-sortable-item="props.field.field" :class="{ dragging: props.dragging }">
        <div class="actions-small-2">
            <span v-if="isSaving" class="loader" />
            <div v-else class="grip compact" data-pe-field-drag-handle />

            <div class="list-item">
                <div class="card small-12 compact">
                    <h3 :title="props.field.displayAs">
                        <pe-fields-icon :field="props.field" />
                        {{ props.field.displayAs }}
                    </h3>
                </div>
            </div>

            <nav>
                <a
                    ref="editBtn"
                    class="icon-settings"
                    title="Settings"
                    :data-test="`${peFieldsStore.getFieldIdentifier(props.field)}-settings`"
                    @click="isEditPanelOpen = !isEditPanelOpen"
                />
                <a
                    ref="removeBtn"
                    class="icon-close"
                    title="Remove"
                    :data-test="`${peFieldsStore.getFieldIdentifier(props.field)}-remove`"
                    @click="isRemovePanelOpen = !isRemovePanelOpen"
                />
            </nav>
        </div>

        <div class="pe-fields-selected-item-panel">
            <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
                <pe-fields-active-item-error v-if="fieldSaveError" :message="fieldSaveError" />
            </Transition>

            <pe-fields-active-item-edit
                v-if="!isAvailableField(props.field)"
                :field="props.field"
                :is-open="isEditPanelOpen && !props.dragging"
                @close="isEditPanelOpen = false"
            />

            <pe-fields-active-item-remove
                v-if="!isAvailableField(props.field)"
                :field="props.field"
                :is-open="isRemovePanelOpen && !props.dragging"
                @close="isRemovePanelOpen = false"
            />
        </div>
    </li>
</template>

<style scoped>
/* Hide the contents of the field placeholder while it is being dragged (shows as a white box) */
li.dragging {
    opacity: 0;
}

/* To ensure the height of the title matches the card.compact height */
h3 {
    min-height: 2.5rem;
}

/* To remove the hover background on the icons since they rotate */
nav > a[class^="icon-"]:hover {
    background-color: transparent;
}

/* The item held by the mouse when dragging */
.dragged-item {
    background-color: var(--content-ui-hover);
    height: auto !important;
}

/*
 * Hide any component panel when this is the item held by the mouse.
 * This must be a style since the element handling the drag will not react to changes here
 */
.dragged-item .pe-fields-selected-item-panel {
    display: none;
}

.loader {
    margin: 0 0.75rem; /* To match the grip handle which this replaces */
}
</style>
