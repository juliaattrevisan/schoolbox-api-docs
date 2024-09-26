<script setup lang="ts">
/**
 * An individual selected field in the profile editor
 */

import { animateRotation } from "@js/util/animateUtils";
import PeFieldsSelectedItemEdit from "./pe-fields-selected-item-edit.vue";
import PeFieldsSelectedItemRemove from "./pe-fields-selected-item-remove.vue";
import type { ProfileSelectedField } from "./types/profileTypes";
import type { ProfileEditorAvailableField } from "./types/profileEditorTypes";
import PeFieldsIcon from "./pe-fields-icon.vue";

interface Props {
    field: ProfileSelectedField | ProfileEditorAvailableField;
    dragging: boolean;
}

const props = defineProps<Props>();

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
</script>

<template>
    <div class="component l-mar-m--bottom" :data-sortable-item="props.field.field">
        <div class="row collapse">
            <div class="small-12 columns">
                <div class="component-titlebar">
                    <div class="actions-small-2">
                        <div class="list-item">
                            <h2 :title="props.field.displayAs">
                                <a
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
                                @click="isEditPanelOpen = !isEditPanelOpen"
                            />
                            <a
                                ref="removeBtn"
                                class="icon-close"
                                title="Close"
                                @click="isRemovePanelOpen = !isRemovePanelOpen"
                            />
                        </nav>
                    </div>
                </div>

                <div v-if="'fieldSettings' in props.field" class="component-panel">
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
</style>
