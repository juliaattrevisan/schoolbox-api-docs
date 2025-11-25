<script setup lang="ts">
/**
 * To display a students DP in a modal
 */
import locale from "@js/terminology/rosettaStone";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import type { Participant } from "../js/types/customParticipationTypes";

const participant = defineModel<Participant | null>("participant", { default: null });
const isOpen = window.Vue.ref(false);

/**
 * Open/close the modal based on participant changes
 */
window.Vue.watch(participant, (newParticipant) => {
    isOpen.value = newParticipant !== null;
});

/**
 * When the modal is closed trigger to clear the participant
 */
window.Vue.watch(isOpen, (newIsOpen) => {
    if (!newIsOpen) {
        participant.value = null;
    }
});

/**
 * Handle close action
 */
function onClose(): void {
    participant.value = null;
}
</script>

<template>
    <basic-modal v-model:is-open="isOpen">
        <div v-if="participant" class="l-flex-col l-gap-m f-no-margins">
            <div class="l-flex-col l-gap-s f-no-margins">
                <h2>
                    {{ locale.differentiationProfile.translate(false, true) }} for {{ participant.user.givenName }}
                    {{ participant.user.lastname }}
                </h2>
            </div>
            <div class="l-flex l-gap-xs f-no-margins">
                <button class="button l-flex__item--1" @click.prevent="onClose">Close</button>
            </div>
        </div>
    </basic-modal>
</template>

<style scoped></style>
