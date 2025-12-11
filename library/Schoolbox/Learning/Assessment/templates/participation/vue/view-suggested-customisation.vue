<script setup lang="ts">
/**
 * View or generate a suggested customisation on the
 * modify/new/copy activity pages.
 */

import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { sbProps } from "@js/util/sbx/pageUtils";
import CpGenAi from "./cp-gen-ai.vue";
import type { CustomisedSuggestionResponse, Participant, Routes } from "../js/types/customParticipationTypes";

const props = defineProps<{
    differentiationEnabled: boolean;
    participant: Participant;
    routes: Routes;
    assessmentId: number;
}>();

const isOpen = window.Vue.ref(false);
const aiResponse = window.Vue.ref<CustomisedSuggestionResponse | null>(null); // to store the generated AI response, which is set within the cp-gen-ai component

const currentParticipant = window.Vue.ref<Participant | null>(null);

/**
 * To view the AI suggested customisation for a participant
 */
function onViewSuggestedCustomisation(): void {
    currentParticipant.value = props.participant;
    isOpen.value = true;
}

/**
 * When a new AI response is generated, store it against the participant
 * so it can be reused if the modal is reopened for the same participant
 * @param newResponse The new AI response generated
 */
function onNewResponse(newResponse: CustomisedSuggestionResponse): void {
    currentParticipant.value!.cachedSuggestedCustomisation = newResponse;
}

// props.participant._links.differentiationProfile
</script>

<template>
    <button
        v-if="props.differentiationEnabled && sbProps.aiFeatures.learningactivitycustomisation?.enabled"
        class="button icon-ai f-no-margins"
        type="button"
        @click.prevent="onViewSuggestedCustomisation"
    >
        {{ props.participant.cachedSuggestedCustomisation ? "View" : "Generate" }} Suggested Customisation
    </button>
    <basic-modal v-model:is-open="isOpen">
        <cp-gen-ai
            v-model:ai-response="aiResponse"
            :route="props.routes.genAI"
            :assessment-id="props.assessmentId"
            :participant="currentParticipant"
            @new-response="onNewResponse"
        />
    </basic-modal>
</template>

<style scoped></style>
