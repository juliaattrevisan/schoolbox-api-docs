<script setup lang="ts">
/**
 * To generate and display a suggested customisation,
 * choose what type of customisation to apply and
 * notify if existing customisation or feedback already exists.
 */

import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import CpGenAi from "./cp-gen-ai.vue";
import CpSelectActivity from "./cp-select-activity.vue";
import type { AIResponse, Routes } from "../js/types/customParticipationTypes";

const props = defineProps<{
    routes: Routes;
    assessmentId: number;
}>();

const studentId = defineModel<number | null>("student-id", { default: null });

const isOpen = window.Vue.ref(false);
const newLoad = window.Vue.ref(true); // to indicate whether to load new AI content, as we may be returning from another status without changing student
const aiResponse = window.Vue.ref<AIResponse | null>(null); // to store the generated AI response, which is set within the cp-gen-ai component
const status = window.Vue.ref<"view" | "selectType">("view"); // to track the current status of the modal

/**
 * To display the view/generate customisation modal when a student is selected
 */
window.Vue.watch(studentId, (newStudentId) => {
    if (newStudentId) {
        newLoad.value = true;
        isOpen.value = true;
    }
});

/**
 * When the modal is closed trigger to clear the studentId
 */
window.Vue.watch(isOpen, (newIsOpen) => {
    if (!newIsOpen) {
        studentId.value = null;
        newLoad.value = true;
        status.value = "view";
    }
});

/**
 * To move to the next step of customising the activity
 */
function onCustomiseActivity(): void {
    status.value = "selectType";
}

/**
 * Close the modal, resetting state to cancel any operation
 */
function onCancel(): void {
    isOpen.value = false;
    studentId.value = null;
}

/**
 * To go back to viewing the suggested customisation
 */
function onBack(): void {
    newLoad.value = false; // to prevent reloading AI content when the cp-gen-ai component is mounted again
    status.value = "view";
}
</script>

<template>
    <basic-modal v-model:is-open="isOpen">
        <cp-gen-ai
            v-if="status === 'view'"
            v-model:ai-response="aiResponse"
            :route="props.routes.genAI"
            :assessment-id="props.assessmentId"
            :student-id="studentId"
            :new-load="newLoad"
            @cancel="onCancel"
            @customise-activity="onCustomiseActivity"
        />
        <cp-select-activity
            v-else-if="status === 'selectType'"
            :routes="props.routes"
            :assessment-id="props.assessmentId"
            :student-id="studentId"
            @back="onBack"
        />
    </basic-modal>
</template>

<style scoped></style>
