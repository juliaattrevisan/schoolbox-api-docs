<script setup lang="ts">
/**
 * To display a Happy Birthday message to the user on their birthday.
 */

import bsNotification from "./bs-notification.vue";

/**
 * To display the birthday modal
 */
function onNotificationSelected() {
    console.warn("click");
    // Handle the notification selected event
    // remove the notification and show the modal, updating the local storage
}
</script>

<template>
    <bs-notification @selected="onNotificationSelected" />
</template>

<style scoped></style>
