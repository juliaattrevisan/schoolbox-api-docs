<script setup lang="ts">
/**
 * To display a Happy Birthday message to the user on their birthday.
 */

import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import BsNotification from "./bs-notification.vue";
import BsModal from "./bs-modal.vue";

const modalEl = window.Vue.ref(null);
const hasSelectedNotification = window.Vue.ref(false);

/**
 * To display the birthday modal
 */
function onNotificationSelected() {
    hasSelectedNotification.value = true;
    modalEl.value.open();
    // remove the notification and show the modal, updating the local storage
}
</script>

<template>
    <bs-notification @selected="onNotificationSelected" />
    <reveal-modal ref="modalEl" :close-on-background-click="false" size="tiny">
        <bs-modal v-if="hasSelectedNotification" />
    </reveal-modal>
</template>

<style scoped></style>
