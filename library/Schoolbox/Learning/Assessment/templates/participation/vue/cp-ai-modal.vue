<script setup lang="ts">
/**
 * To display or regenerate AI content for a studentId customisation
 * This modal is displayed both on a Student Participation page as well
 * as on the modify/new/copy activity pages.
 */

import locale from "@js/terminology/rosettaStone";
import { apiRequest } from "@js/util/sbx/apiUtils";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import LoadingCards from "@schoolboxVue/components/loading/loading-cards.vue";
import MediaArticle from "@schoolboxVue/components/media-article.vue";

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

const isOpen = defineModel<boolean>("is-open", { default: false }); // to open/close the modal
const studentId = defineModel<number | null>("student-id", { default: null });

const emit = defineEmits<{
    (event: "customiseActivity"): void;
}>();

const isProcessing = window.Vue.ref(false);
const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the create
const aiResponse = window.Vue.ref<AIResponse | null>(null);
let controller: AbortController; // to allow aborting the previous search if a new one is started

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
        aiResponse.value = null;
        controller = new AbortController();
        const response = await apiRequest<AIResponse, { assessmentId: number; studentId: number | null }>({
            url: props.route,
            method: "POST",
            signal: controller.signal,
            body: {
                assessmentId: props.assessmentId,
                studentId: studentId.value,
            },
        });
        if (!response.success) {
            throw new Error(response.message || `Failed to retrieve customisation`);
        }
        aiResponse.value = response.data || null;
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
    // if (!newIsOpen) {
    //     studentId.value = null;
    //     aiResponse.value = null;
    // }
});

/**
 * to hide the generate AI prompt
 */
function onCancel(): void {
    isOpen.value = false;
    if (isProcessing.value) {
        controller.abort();
    }
}

/**
 * To use the suggested AI content
 */
function onConfirm(): void {
    emit("customiseActivity");
}

/**
 * To regenerate the AI content
 */
function onRegenerate(): void {
    void load();
}
</script>

<template>
    <basic-modal v-model:is-open="isOpen">
        <form class="l-flex-col l-gap-m f-no-margins" @submit.prevent="onConfirm">
            <div class="l-flex-row l-flex--justify-space l-gap-s f-no-margins">
                <h2>Suggested Customisation</h2>
                <button v-if="aiResponse" class="button icon-ai" @click.prevent="onRegenerate">Regenerate</button>
            </div>
            <request-error v-if="loadError" :further-info="loadError" />
            <div v-else-if="isProcessing">
                <loading-cards :rows="1" :show-image="false">
                    <span class="title-placeholder"></span>
                    <p></p>
                    <p></p>
                </loading-cards>
                <loading-cards :rows="1" :show-image="false">
                    <span class="title-placeholder"></span>
                    <p></p>
                    <p></p>
                </loading-cards>
            </div>
            <div v-else-if="studentId && aiResponse" class="l-flex-col l-gap-m f-no-margins">
                <div class="c-vert-scroll-container l-flex-col l-gap-s">
                    <div class="l-flex-col l-gap-s f-no-margins">
                        <div class="l-flex-col l-gap-xs f-no-margins">
                            <h3>
                                <strong>Considerations</strong>
                            </h3>
                            <media-article :content="aiResponse?.suggested_considerations as string" />
                        </div>
                        <div class="l-flex-col l-gap-xs f-no-margins">
                            <h3>
                                <strong>Suggested Learning {{ locale.activity.translate(false, true) }} Update</strong>
                            </h3>
                            <media-article :content="aiResponse?.suggested_learning_activity_update as string" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="l-flex l-gap-xs f-no-margins">
                <button class="button l-flex__item--1" data-test="cp-ai-modal-cancel" @click.prevent="onCancel">
                    Cancel
                </button>
                <button
                    v-if="!isProcessing && aiResponse"
                    class="button submit l-flex__item--1"
                    type="button"
                    data-test="cp-ai-modal-confirm"
                    @click.prevent="onConfirm"
                >
                    Customise {{ locale.activity.translate(false, true) }}
                </button>
            </div>
        </form>
    </basic-modal>
</template>

<style scoped></style>
