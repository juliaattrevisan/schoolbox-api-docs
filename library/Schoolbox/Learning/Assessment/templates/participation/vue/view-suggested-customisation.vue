<script setup lang="ts">
/**
 * View or generate a suggested customisation on the
 * modify/new/copy activity pages.
 */

import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import CpGenAi from "./cp-gen-ai.vue";
import type { CustomisedSuggestionResponse, Participant, Routes } from "../js/types/customParticipationTypes";

const props = defineProps<{
    participant: Participant;
    routes: Routes;
    assessmentId: number;
}>();

const isOpen = window.Vue.ref(false);
const participant = window.Vue.ref(props.participant);
const aiResponse = window.Vue.ref<CustomisedSuggestionResponse | null>(null); // to store the generated AI response, which is set within the cp-gen-ai component

/**
 * When a new AI response is generated, store it against the participant
 * so it can be reused if the modal is reopened for the same participant
 * @param newResponse The new AI response generated
 */
function onNewResponse(newResponse: CustomisedSuggestionResponse): void {
    participant.value.cachedSuggestedCustomisation = newResponse;
}
</script>

<template>
    <basic-modal v-model:is-open="isOpen">
        <cp-gen-ai
            v-model:ai-response="aiResponse"
            :route="props.routes.genAI"
            :assessment-id="props.assessmentId"
            :participant="participant"
            @new-response="onNewResponse"
        />
    </basic-modal>
</template>

<style scoped></style>
