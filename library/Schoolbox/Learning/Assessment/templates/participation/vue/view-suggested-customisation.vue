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
    editorId: string;
}>();

const isOpen = window.Vue.ref(false);
const aiResponse = window.Vue.ref<CustomisedSuggestionResponse | null>(null); // to store the generated AI response, which is set within the cp-gen-ai component
const currentParticipant = window.Vue.ref<Participant | null>(null);

/**
 * To view the AI suggested customisation for a participant
 */
function onViewSuggestedCustomisation(): void {
    currentParticipant.value = props.participant;
    // set the cached AI response if it exists to avoid regenerating
    if (currentParticipant.value?.cachedSuggestedCustomisation) {
        aiResponse.value = currentParticipant.value.cachedSuggestedCustomisation;
    }
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

/**
 * Reset the modal state to initial
 * for a possible reopening
 */
function resetState(): void {
    isOpen.value = false;
    currentParticipant.value = null;
    aiResponse.value = null;
}

/**
 * Close the modal, resetting state to cancel any operation
 */
function onCancel(): void {
    resetState();
}

/**
 * Insert the copy of the suggested learning activity update into the activity description
 * We should be pretty confident the description editor exists without thinking about
 * not showing the button when it does not
 */
function onAddToDescription(): void {
    const editor = window.sbProps.editors[props.editorId];
    if (editor) {
        const extraSpace = editor.getData() === "" ? "" : "<p></p>"; // add extra line break if the editor content is not empty
        editor.setData(`${editor.getData()}${extraSpace}${aiResponse.value?.suggested_learning_activity_update || ""}`);
    }
    resetState();
}
</script>

<template>
    <button
        v-if="
            props.differentiationEnabled &&
            sbProps.aiFeatures.learningactivitycustomisation?.enabled &&
            props.participant._links.differentiationProfile
        "
        class="button f-no-margins sbx-button--has-icon"
        type="button"
        @click.prevent="onViewSuggestedCustomisation"
    >
        <sbx-icon name="ai"></sbx-icon>
        {{ props.participant.cachedSuggestedCustomisation ? "View" : "Generate" }} Suggested Customisation
    </button>
    <basic-modal v-model:is-open="isOpen">
        <cp-gen-ai
            v-model:ai-response="aiResponse"
            :route="props.routes.genAI"
            :assessment-id="props.assessmentId"
            :participant="currentParticipant"
            confirm-text="Add to Description"
            @new-response="onNewResponse"
            @cancel="onCancel"
            @confirm="onAddToDescription"
        />
    </basic-modal>
</template>

<style scoped></style>
