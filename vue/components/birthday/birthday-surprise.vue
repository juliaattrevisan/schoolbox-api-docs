<script setup lang="ts">
/**
 * To display a Happy Birthday message to the user on their birthday.
 */

import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import BsNotification from "./bs-notification.vue";

const modalEl = window.Vue.ref(null);

/**
 * To display the birthday modal
 */
function onNotificationSelected() {
    modalEl.value.open();
    // remove the notification and show the modal, updating the local storage
}
</script>

<template>
    <bs-notification @selected="onNotificationSelected" />
    <reveal-modal ref="modalEl" :close-on-background-click="false">
        <h2>Please Confirm</h2>
    </reveal-modal>
</template>

<style scoped></style>
