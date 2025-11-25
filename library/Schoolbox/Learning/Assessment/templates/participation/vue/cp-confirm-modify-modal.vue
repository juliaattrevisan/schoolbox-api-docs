<script setup lang="ts">
/**
 * Custom Participation Confirm Modify Modal Component
 */
import locale from "@js/terminology/rosettaStone";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import type { Participant } from "../js/types/customParticipationTypes";

defineProps<{
    participantsWithInteractions: Participant[];
}>();

const isOpen = defineModel<boolean>("isOpen", { default: false });

const emit = defineEmits<{
    (event: "confirm"): void;
}>();

/**
 * Handle cancel action
 */
function handleCancel(): void {
    isOpen.value = false;
}

/**
 * Handle confirm action
 */
function handleConfirm(): void {
    emit("confirm");
}
</script>

<template>
    <basic-modal v-model:is-open="isOpen">
        <div class="l-flex-col l-gap-m f-no-margins">
            <div class="l-flex-col l-gap-s f-no-margins">
                <h2>Please confirm</h2>
                <p class="f-no-margins">
                    The following student(s) have submissions and/or feedback for this
                    {{ locale.assessment.singular() }}.
                </p>
                <div class="l-flex-col l-gap-xs">
                    <div
                        v-for="participant in participantsWithInteractions"
                        :key="participant.user.id"
                        class="l-flex l-gap-s l-align-center"
                    >
                        <img
                            :src="`/portrait.php?id=${participant.user.id}&size=square32`"
                            :alt="`${participant.user.lastname}, ${participant.user.firstname}`"
                        />
                        <p>
                            {{
                                participant.user.preferredName
                                    ? participant.user.preferredName
                                    : participant.user.firstname
                            }}
                            {{ participant.user.lastname }}
                        </p>
                    </div>
                </div>
                <p>
                    Exclusion from this {{ locale.assessment.singular() }} will remove access to these submissions
                    and/or feedback. Proceed?
                </p>
            </div>
            <div class="l-flex l-gap-xs f-no-margins">
                <button class="button submit l-flex__item--1" @click.prevent="handleConfirm">Yes</button>
                <button class="button l-flex__item--1" @click.prevent="handleCancel">Cancel</button>
            </div>
        </div>
    </basic-modal>
</template>

<style scoped></style>
