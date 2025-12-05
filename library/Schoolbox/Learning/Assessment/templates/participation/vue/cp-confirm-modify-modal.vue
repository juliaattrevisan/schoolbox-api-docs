<script setup lang="ts">
/**
 * Custom Participation Confirm Modify Modal Component
 */
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import CpConfirmModify from "./cp-confirm-modify.vue";
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
function onCancel(): void {
    isOpen.value = false;
}

/**
 * Handle confirm action
 */
function onConfirm(): void {
    emit("confirm");
}
</script>

<template>
    <basic-modal v-model:is-open="isOpen">
        <cp-confirm-modify :participants="participantsWithInteractions" @confirm="onConfirm" @cancel="onCancel" />
    </basic-modal>
</template>

<style scoped></style>
