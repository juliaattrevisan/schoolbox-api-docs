<script setup lang="ts">
/**
 * To display or regenerate AI content for a student customisation
 * This content is displayed both on a Student Participation page as well
 * as on the modify/new/copy activity pages.
 */

import locale from "@js/terminology/rosettaStone";
import { apiRequest } from "@js/util/sbx/apiUtils";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import LoadingCards from "@schoolboxVue/components/loading/loading-cards.vue";
import MediaArticle from "@schoolboxVue/components/media-article.vue";
import type { CustomisedSuggestionResponse, Participant } from "../js/types/customParticipationTypes";

const props = defineProps<{
    route: string;
    assessmentId: number;
    participant: Participant | null;
    confirmText: string;
}>();

const emit = defineEmits<{
    (event: "newResponse", response: CustomisedSuggestionResponse): void;
    (event: "cancel"): void;
    (event: "confirm"): void;
}>();

const aiResponse = defineModel<CustomisedSuggestionResponse | null>("ai-response", { default: null });

const isProcessing = window.Vue.ref(false);
const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the create
let controller: AbortController | null = null; // to allow aborting the previous search if a new one is started

/**
 * Load the AI customised suggestion for the participant
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
        const response = await apiRequest<
            CustomisedSuggestionResponse,
            { assessmentId: number; studentId: number | null; regenerate: number }
        >({
            url: props.route,
            method: "POST",
            signal: controller.signal,
            body: {
                assessmentId: props.assessmentId,
                studentId: props.participant?.user.id ?? null,
                regenerate: 1,
            },
        });
        if (!response.success) {
            throw new Error(response.message || `Failed to retrieve customisation`);
        }
        aiResponse.value = response.data || null;
        emit("newResponse", response.data);
    } catch (error) {
        const errorMessage = error instanceof Error ? error.message : "There was an unexpected error";
        loadError.value = errorMessage;
    } finally {
        isProcessing.value = false;
    }
}

/**
 * to hide the generate AI prompt
 */
function onCancel(): void {
    emit("cancel");
    if (isProcessing.value && controller) {
        controller.abort();
    }
}

/**
 * To use the suggested AI content
 */
function onConfirm(): void {
    emit("confirm");
}

/**
 * To regenerate the AI content
 */
function onRegenerate(): void {
    void load();
}

/**
 * On mounted check if we need to load AI content,
 * we may not need to if we are revisiting without
 * selecting a new participant or requesting regeneration
 */
window.Vue.onMounted(() => {
    if (props.participant && aiResponse.value === null) {
        void load();
    }
});
</script>

<template>
    <form class="l-flex-col l-gap-m f-no-margins" @submit.prevent="onConfirm">
        <div class="l-flex-row l-flex--justify-space l-gap-s f-no-margins">
            <h2>
                Suggested Customisation
                {{ props.participant?.user.fullName ? ` for ${props.participant.user.fullName}` : "" }}
            </h2>
            <button v-if="aiResponse" class="button" @click.prevent="onRegenerate">
                <sbx-icon name="ai"></sbx-icon>
                Regenerate
            </button>
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
        <div v-else-if="participant && aiResponse" class="l-flex-col l-gap-s f-no-margins">
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
            <span class="meta">AI sometimes gets things wrong — please check important details.</span>
        </div>
        <div class="l-flex l-gap-xs f-no-margins">
            <button class="button l-flex__item--1" data-test="cp-ai-modal-cancel" @click.prevent="onCancel">
                {{ isProcessing ? "Cancel" : "Close" }}
            </button>
            <button
                v-if="!isProcessing && aiResponse"
                class="button submit l-flex__item--1"
                type="button"
                data-test="cp-ai-modal-confirm"
                @click.prevent="onConfirm"
            >
                {{ props.confirmText }}
            </button>
        </div>
    </form>
</template>

<style scoped></style>
