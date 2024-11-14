<script setup lang="ts">
/**
 * An individual mandatory field in the profile editor
 * This is currently just used for the portrait image field, but it's designed to work for any field
 */

import { animateRotation } from "@js/util/animateUtils";
import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import { useProfileEditorFieldsStore } from "./store/profileEditorFieldsStore";
import PeFieldsActiveItemEdit from "./pe-fields-active-item-edit.vue";
import PePanelError from "./pe-panel-error.vue";
import PeFieldsIcon from "./pe-fields-icon.vue";
import type { ProfileEditorMandatoryField } from "./types/profileEditorTypes";

interface Props {
    field: ProfileEditorMandatoryField;
}

const props = defineProps<Props>();
const peFieldsStore = useProfileEditorFieldsStore();

const editBtn = window.Vue.ref<HTMLElement | null>(null);
const isEditPanelOpen = window.Vue.ref<boolean>(false);

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
});

/**
 * The error message to display if there was a problem saving this field
 */
const fieldSaveError = window.Vue.computed(() => {
    return peFieldsStore.actionErrors[props.field.field];
});
</script>

<template>
    <div class="row actions-small-1">
        <div class="list-item">
            <div class="small-12 island">
                <h3 class="subheader" :title="peFieldsStore.getFieldName(props.field)">
                    <pe-fields-icon :field="props.field" />
                    {{ peFieldsStore.getFieldName(props.field) }}
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
        </nav>
    </div>

    <section>
        <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
            <pe-panel-error v-if="fieldSaveError" :message="fieldSaveError" type="field" />
        </Transition>

        <pe-fields-active-item-edit :field="props.field" :is-open="isEditPanelOpen" @close="isEditPanelOpen = false" />

        <!-- hardcoded for field type portrait... shows a sample portrait image -->
        <img
            v-if="props.field.field === 'portrait'"
            src="/portrait.php?id=0&size=constrain200"
            srcset="/portrait.php?id=0&size=constrain200 200w, /portrait.php?id=0&size=constrain500 500w"
            sizes="(max-width: 1300px) 200px, 500px"
            class="full-width hide-for-large-down l-pad-m"
        />
    </section>
</template>

<style scoped>
/* To remove the hover background on the icons since they rotate */
nav > a[class^="icon-"]:hover {
    background-color: transparent !important;
}
</style>
