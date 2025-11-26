<script setup lang="ts">
/**
 * To display a students DP in a modal
 */

import locale from "@js/terminology/rosettaStone";
import { apiRequest } from "@js/util/sbx/apiUtils";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import type { User } from "@schoolboxLibrary/types/User";

const props = defineProps<{
    route: string;
}>();

const student = defineModel<User | null>("student", { default: null });
const isOpen = window.Vue.ref(false);
const isProcessing = window.Vue.ref(false);
const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the create

/**
 * Load the DP
 */
async function load(): Promise<void> {
    if (props.route === "") {
        loadError.value = "No route specified";
        return;
    }
    const toast = window.sbProps.toastActivate("Loading...");
    try {
        isProcessing.value = true;
        loadError.value = null;
        const response = await apiRequest<{ id: number }>({
            url: props.route,
            method: "GET",
        });
        if (!response.success) {
            throw new Error(
                response.message || `Failed to retrieve ${locale.differentiationProfile.translate(false, true)}`,
            );
        }
        debugger;
        toast.timeout({ text: `${locale.differentiationProfile.translate(false, true)} successfully retrieved` });
    } catch (error) {
        const errorMessage = error instanceof Error ? error.message : "There was an unexpected error";
        loadError.value = errorMessage;
        toast.timeout({ text: errorMessage, isSuccess: false });
    } finally {
        isProcessing.value = false;
    }
}

/**
 * Open/close the modal based on student changes
 */
window.Vue.watch(student, (newUser) => {
    isOpen.value = newUser !== null;
    if (newUser) {
        void load();
    }
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
        <div v-if="isProcessing" class="loader"></div>
        <data-load-error
            v-else-if="loadError"
            :data-title="locale.differentiationProfile.translate(false, true)"
            :further-info="loadError"
        />
        <div v-else-if="student" class="l-flex-col l-gap-m f-no-margins">
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
