<script setup lang="ts">
/**
 * To display or regenerate AI content for a studentId customisation
 */

import locale from "@js/terminology/rosettaStone";
import { apiRequest } from "@js/util/sbx/apiUtils";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import MediaArticle from "@schoolboxVue/components/media-article.vue";
import type { User } from "@schoolboxLibrary/types/User";

/**
 * The expected AI response type
 */
type AIResponse = {
    suggested_considerations: string;
    suggested_learning_activity_update: string;
};

const props = defineProps<{
    route: string;
    assessmentId: number;
}>();

const studentId = defineModel<number | null>("student-id", { default: null });
const isOpen = window.Vue.ref(false);
const isProcessing = window.Vue.ref(false);
const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the create
const responseData = window.Vue.ref<AIResponse | null>(null);
// const data = window.Vue.ref<Item[]>([]);
// const adjustmentData = window.Vue.ref<AdjustmentItem[]>([]);
// const textData = window.Vue.ref<TextItem[]>([]);

/**
 * Load the DP
 */
async function load(): Promise<void> {
    if (props.route === "") {
        loadError.value = "No route specified";
        return;
    }
    try {
        isProcessing.value = true;
        loadError.value = null;
        const response = await apiRequest<AIResponse, { assessmentId: number; studentId: number | null }>({
            url: props.route,
            method: "POST",
            body: {
                assessmentId: props.assessmentId,
                studentId: studentId.value,
            },
        });
        if (!response.success) {
            throw new Error(response.message || `Failed to retrieve customisation`);
        }
        responseData.value = response.data || null;
    } catch (error) {
        const errorMessage = error instanceof Error ? error.message : "There was an unexpected error";
        loadError.value = errorMessage;
    } finally {
        isProcessing.value = false;
    }
}

/**
 * Open/close the modal based on studentId changes
 */
window.Vue.watch(studentId, (newStudentId) => {
    isOpen.value = newStudentId !== null;
    if (newStudentId && newStudentId !== null) {
        void load();
    }
});

/**
 * When the modal is closed trigger to clear the studentId
 */
window.Vue.watch(isOpen, (newIsOpen) => {
    if (!newIsOpen) {
        studentId.value = null;
    }
});

/**
 * Handle close action
 */
function onClose(): void {
    studentId.value = null;
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
        <div v-else-if="studentId && responseData" class="l-flex-col l-gap-m f-no-margins">
            <div class="l-flex-col l-gap-s f-no-margins">
                <h2>Suggested Customisation</h2>
            </div>
            <div class="c-vert-scroll-container l-flex-col l-gap-s">
                <div class="l-flex-col l-gap-s f-no-margins">
                    <div class="l-flex-col l-gap-xs f-no-margins">
                        <h3>
                            <strong>Considerations</strong>
                        </h3>
                        <media-article :content="responseData?.suggested_considerations as string" />
                    </div>
                    <div class="l-flex-col l-gap-xs f-no-margins">
                        <h3>
                            <strong>Suggested Learning {{ locale.activity.translate(false, true) }} Update</strong>
                        </h3>
                        <media-article :content="responseData?.suggested_learning_activity_update as string" />
                    </div>
                </div>
            </div>
            <div class="l-flex l-gap-xs f-no-margins">
                <button class="button l-flex__item--1" @click.prevent="onClose">Close</button>
            </div>
        </div>
    </basic-modal>
</template>

<style scoped></style>
