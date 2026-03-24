<script setup lang="ts">
/**
 * To generate and display a suggested customisation,
 * choose what type of customisation to apply and
 * notify if existing customisation or feedback already exists.
 */

import locale from "@js/terminology/rosettaStone";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import CpGenAi from "./cp-gen-ai.vue";
import CpSelectActivity from "./cp-select-activity.vue";
import CpConfirmModify from "./cp-confirm-modify.vue";
import type { CustomisedSuggestionResponse, Participant, Routes } from "../js/types/customParticipationTypes";
import { hasCustomisation } from "../js/util/participantUtils";

const props = defineProps<{
    routes: Routes;
    assessmentId: number;
}>();

const participant = defineModel<Participant | null>("participant", { default: null });

const isOpen = window.Vue.ref(false);
const aiResponse = window.Vue.ref<CustomisedSuggestionResponse | null>(null); // to store the generated AI response, which is set within the cp-gen-ai component
const status = window.Vue.ref<"view" | "confirm" | "selectType">("view"); // to track the current status of the modal
const previousStatus = window.Vue.ref<"view" | "confirm" | "selectType">("view"); // to track the previous status for animation direction
const confirmStatusText = window.Vue.ref("");
const confirmProceedText = window.Vue.ref("");

/**
 * To display the view/generate customisation modal when a participant is selected
 */
window.Vue.watch(participant, (newParticipant) => {
    // open the modal when a new participant is set
    if (newParticipant) {
        isOpen.value = true;
    }
    // set the cached AI response if it exists to avoid regenerating
    if (newParticipant && newParticipant.cachedSuggestedCustomisation) {
        aiResponse.value = newParticipant.cachedSuggestedCustomisation;
    }
});

/**
 * When the modal is closed trigger to clear the participant and reset status
 */
window.Vue.watch(isOpen, (newIsOpen) => {
    if (!newIsOpen) {
        participant.value = null;
        aiResponse.value = null;
        status.value = "view";
        previousStatus.value = "view";
    }
});

/**
 * Track status changes to determine animation direction
 */
window.Vue.watch(status, (newStatus, oldStatus) => {
    previousStatus.value = oldStatus;
});

/**
 * Compute animation direction based on status progression
 */
const transitionName = window.Vue.computed(() => {
    const statusOrder = ["view", "confirm", "selectType"];
    const currentIndex = statusOrder.indexOf(status.value);
    const previousIndex = statusOrder.indexOf(previousStatus.value);
    return currentIndex > previousIndex ? "slide-left" : "slide-right";
});

/**
 * When a new AI response is generated, store it against the participant
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
}

/**
 * To move to the next step of customising the activity
 */
function onCustomiseActivity(): void {
    // has a customisation already but no submission
    if (hasCustomisation(participant.value!) && !participant.value?.hasInteraction) {
        status.value = "confirm";
        confirmStatusText.value = "A customisation already exists for this student.";
        confirmProceedText.value = `Proceeding will exclude them from this ${locale.activity.singular()} and create a new customisation. Proceed?`;
        return;
    }
    // has both customisation and submission/feedback
    if (hasCustomisation(participant.value!) && participant.value?.hasInteraction) {
        status.value = "confirm";
        confirmStatusText.value = "A customisation with a submission and/or feedback already exists for this student.";
        confirmProceedText.value = `Proceeding will exclude them from this ${locale.activity.singular()} and remove access to these submissions and/or feedback. Proceed?`;
        return;
    }
    // has submission/feedback on the existing activity
    if (participant.value?.hasInteraction) {
        status.value = "confirm";
        confirmStatusText.value = "A submission and/or feedback already exists for this student.";
        confirmProceedText.value = `Proceeding will exclude them from this ${locale.activity.singular()} and remove access to their submission and/or feedback. Proceed?`;
        return;
    }
    status.value = "selectType";
}

/**
 * To go back to viewing the suggested customisation
 */
function onBack(): void {
    status.value = "view";
}

/**
 * Confirm we still want to modify existing customisation
 */
function onConfirm(): void {
    status.value = "selectType";
}
</script>

<template>
    <basic-modal v-model:is-open="isOpen">
        <Transition :name="transitionName" mode="out-in">
            <cp-gen-ai
                v-if="status === 'view'"
                key="view"
                v-model:ai-response="aiResponse"
                :route="props.routes.genAI"
                :assessment-id="props.assessmentId"
                :participant="participant"
                :confirm-text="`Customise ${locale.activity.translate(false, true)}`"
                @new-response="onNewResponse"
                @cancel="onCancel"
                @confirm="onCustomiseActivity"
            />
            <cp-confirm-modify
                v-else-if="status === 'confirm'"
                key="confirm"
                :participants="participant ? [participant] : []"
                :show-participants="false"
                @cancel="onCancel"
                @confirm="onConfirm"
            >
                <template #status-text>
                    {{ confirmStatusText }}
                </template>
                <template #proceed-text>
                    {{ confirmProceedText }}
                </template>
            </cp-confirm-modify>
            <cp-select-activity
                v-else-if="status === 'selectType'"
                key="selectType"
                :routes="props.routes"
                :assessment-id="props.assessmentId"
                :participant="participant"
                @back="onBack"
            />
        </Transition>
    </basic-modal>
</template>

<style scoped>
/* Slide left (forward progression) */
.slide-left-enter-active,
.slide-left-leave-active {
    transition: all 0.3s ease;
}

.slide-left-enter-from {
    opacity: 0;
    transform: translateX(var(--sbx-size-xs, 0.5rem));
}

.slide-left-leave-to {
    opacity: 0;
    transform: translateX(calc(-1 * var(--sbx-size-xs, 0.5rem)));
}

/* Slide right (backward progression) */
.slide-right-enter-active,
.slide-right-leave-active {
    transition: all 0.3s ease;
}

.slide-right-enter-from {
    opacity: 0;
    transform: translateX(calc(-1 * var(--sbx-size-xs, 0.5rem)));
}

.slide-right-leave-to {
    opacity: 0;
    transform: translateX(var(--sbx-size-xs, 0.5rem));
}
</style>
