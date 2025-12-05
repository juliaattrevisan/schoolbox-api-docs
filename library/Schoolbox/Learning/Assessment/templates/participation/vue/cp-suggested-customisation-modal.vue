<script setup lang="ts">
/**
 * To generate and display a suggested customisation,
 * choose what type of customisation to apply and
 * notify if existing customisation or feedback already exists.
 */

import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import CpGenAi from "./cp-gen-ai.vue";
import CpSelectActivity from "./cp-select-activity.vue";
import type { CustomisedSuggestionResponse, Participant, Routes } from "../js/types/customParticipationTypes";

const props = defineProps<{
    routes: Routes;
    assessmentId: number;
}>();

const participant = defineModel<Participant | null>("participant", { default: null });

const isOpen = window.Vue.ref(false);
const aiResponse = window.Vue.ref<CustomisedSuggestionResponse | null>(null); // to store the generated AI response, which is set within the cp-gen-ai component
const status = window.Vue.ref<"view" | "selectType">("view"); // to track the current status of the modal

/**
 * To display the view/generate customisation modal when a participant is selected
 */
window.Vue.watch(participant, (newParticipant) => {
    if (newParticipant) {
        isOpen.value = true;
    }
    if (newParticipant && newParticipant.cachedSuggestedCustomisation) {
        aiResponse.value = newParticipant.cachedSuggestedCustomisation;
    }
});

/**
 * When the modal is closed trigger to clear the participant
 */
window.Vue.watch(isOpen, (newIsOpen) => {
    if (!newIsOpen) {
        participant.value = null;
        status.value = "view";
    }
});

/**
 * When a new AI response is generated, store it against the participant\
 * so it can be reused if the modal is reopened for the same participant
 * @param newResponse The new AI response generated
 */
function onNewResponse(newResponse: CustomisedSuggestionResponse): void {
    participant.value!.cachedSuggestedCustomisation = newResponse;
}

/**
 * Close the modal, resetting state to cancel any operation
 */
function onCancel(): void {
    isOpen.value = false;
    participant.value = null;
}

/**
 * To move to the next step of customising the activity
 */
function onCustomiseActivity(): void {
    status.value = "selectType";
}

/**
 * To go back to viewing the suggested customisation
 */
function onBack(): void {
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
            :participant="participant"
            @new-response="onNewResponse"
            @cancel="onCancel"
            @customise-activity="onCustomiseActivity"
        />
        <cp-select-activity
            v-else-if="status === 'selectType'"
            :routes="props.routes"
            :assessment-id="props.assessmentId"
            :participant="participant"
            @back="onBack"
        />
    </basic-modal>
</template>

<style scoped></style>
