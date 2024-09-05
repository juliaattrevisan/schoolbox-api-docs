<script setup lang="ts">
/**
 * To display a Happy Birthday message to the user on their birthday.
 */

import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import BsNotification from "./bs-notification.vue";
import BsCard from "./bs-card.vue";

const modalEl = window.Vue.ref<InstanceType<typeof RevealModal> | null>(null);
const isModalActive = window.Vue.ref(false); /*  */

/**
 * To display the birthday modal
 */
function onNotificationSelected(): void {
    isModalActive.value = true;
    if (modalEl.value === null) return; // no modal yet
    modalEl.value.open();
    // update the local storage to prevent the modal from showing again
}
</script>

<template>
    <bs-notification @selected="onNotificationSelected" />
    <reveal-modal ref="modalEl" :close-on-background-click="false" size="tiny" @closed="isModalActive = false">
        <bs-card v-if="isModalActive" />
    </reveal-modal>
</template>

<style scoped></style>
