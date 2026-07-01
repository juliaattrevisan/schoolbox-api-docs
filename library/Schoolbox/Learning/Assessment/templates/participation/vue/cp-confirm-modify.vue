<script setup lang="ts">
/**
 * Custom Participation Confirm Modify Component
 */
import locale from "@js/terminology/rosettaStone";
import type { Participant } from "../js/types/customParticipationTypes";

withDefaults(
    defineProps<{
        participants: Participant[];
        showParticipants?: boolean;
    }>(),
    {
        showParticipants: true, // set to false when we already know the single participant involved
    },
);

const emit = defineEmits<{
    (event: "confirm"): void;
    (event: "cancel"): void;
}>();

/**
 * Handle cancel action
 */
function onCancel(): void {
    emit("cancel");
}

/**
 * Handle confirm action
 */
function onConfirm(): void {
    emit("confirm");
}
</script>

<template>
    <div class="u-flex-col u-gap-m f-no-margins">
        <div class="u-flex-col u-gap-s f-no-margins">
            <h2>Please confirm</h2>
            <p class="f-no-margins">
                <slot name="status-text">
                    The following student(s) have submissions and/or feedback for this
                    {{ locale.assessment.singular() }}.
                </slot>
            </p>
            <div v-if="showParticipants" class="u-flex-col u-gap-xs">
                <div
                    v-for="participant in participants"
                    :key="participant.user.id"
                    class="u-flex u-gap-s u-flex--align-center"
                >
                    <img
                        :src="`/portrait.php?id=${participant.user.id}&size=square32`"
                        :alt="`${participant.user.lastname}, ${participant.user.firstname}`"
                    />
                    <p class="f-no-margins">
                        {{
                            participant.user.preferredName ? participant.user.preferredName : participant.user.firstname
                        }}
                        {{ participant.user.lastname }}
                    </p>
                </div>
            </div>
            <p>
                <slot name="proceed-text">
                    Exclusion from this {{ locale.assessment.singular() }} will remove access to these submissions
                    and/or feedback. Proceed?
                </slot>
            </p>
        </div>
        <div class="u-flex u-gap-xs f-no-margins">
            <button
                class="button submit u-flex__item--1"
                data-test="cp-confirm-modify-modal-yes-button"
                @click.prevent="onConfirm"
            >
                Yes
            </button>
            <button
                class="button u-flex__item--1"
                data-test="cp-confirm-modify-modal-cancel-button"
                @click.prevent="onCancel"
            >
                Cancel
            </button>
        </div>
    </div>
</template>

<style scoped></style>
