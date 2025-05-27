<script setup lang="ts">
/**
 * To display a Happy Birthday message to the user on their birthday.
 */

import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import BsNotification from "./bs-notification.vue";
import BsCard from "./bs-card.vue";

const modalEl = window.Vue.ref<InstanceType<typeof RevealModal> | null>(null);
const isModalActive = window.Vue.ref(false); // to remove the content inside the modal when the user closes it

interface Props {
    userId?: number;
}

const props = withDefaults(defineProps<Props>(), {
    userId: 0,
});

/**
 * To display the birthday modal
 */
function onNotificationSelected(): void {
    isModalActive.value = true;
    if (modalEl.value === null) return; // no modal yet
    modalEl.value.open();
    const utc = new Date();
    // Set the expiration to the end of the day, so it will only check it once a day
    utc.setUTCHours(23, 59, 59, 999);
    // update the local storage to prevent the modal from showing again
    document.cookie = `birthdayCelebrated_${props.userId}=1; expires=${utc.toUTCString()}; path=/`;
}
</script>

<template>
    <bs-notification @selected="onNotificationSelected" />
    <reveal-modal ref="modalEl" :close-on-background-click="false" size="tiny" @closed="isModalActive = false">
        <bs-card v-if="isModalActive" />
    </reveal-modal>
</template>

<style scoped></style>
