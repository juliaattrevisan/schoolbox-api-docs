<script setup lang="ts">
/**
 * To display a students DP in a modal
 */

import locale from "@js/terminology/rosettaStone";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import type { User } from "@schoolboxLibrary/types/User";

const props = defineProps<{
    route: string;
}>();

const student = defineModel<User | null>("student", { default: null });
const isOpen = window.Vue.ref(false);

/**
 * Open/close the modal based on student changes
 */
window.Vue.watch(student, (newUser) => {
    isOpen.value = newUser !== null;
});

/**
 * When the modal is closed trigger to clear the student
 */
window.Vue.watch(isOpen, (newIsOpen) => {
    if (!newIsOpen) {
        student.value = null;
    }
});

/**
 * Handle close action
 */
function onClose(): void {
    student.value = null;
}
</script>

<template>
    <basic-modal v-model:is-open="isOpen">
        <div v-if="student" class="l-flex-col l-gap-m f-no-margins">
            <div class="l-flex-col l-gap-s f-no-margins">
                <h2>
                    {{ locale.differentiationProfile.translate(false, true) }} for {{ student.givenName }}
                    {{ student.lastname }}
                </h2>
            </div>
            <div class="l-flex l-gap-xs f-no-margins">
                <button class="button l-flex__item--1" @click.prevent="onClose">Close</button>
            </div>
        </div>
    </basic-modal>
</template>

<style scoped></style>
