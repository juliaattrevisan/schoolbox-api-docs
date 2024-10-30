<script setup lang="ts">
/**
 * A modal to browse and select courses
 */

import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import { useModalStore } from "./store/coseModalStore";

const modalEl = window.Vue.ref<InstanceType<typeof RevealModal> | null>();
const modalStore = useModalStore();

/**
 * to open the modal when requested
 */
window.Vue.watch(
    () => modalStore.isOpen,
    (isOpen) => {
        if (isOpen) {
            modalEl.value?.open();
        } else {
            modalEl.value?.close();
        }
    },
);

/**
 * to close the modal
 */
function onModalClosed(): void {
    modalStore.close();
}
</script>

<template>
    <reveal-modal ref="modalEl" :close-on-background-click="false" @closed="onModalClosed">
        <p>Yep</p>
    </reveal-modal>
</template>

<style scoped></style>
