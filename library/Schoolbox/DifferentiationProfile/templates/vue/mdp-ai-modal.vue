<script setup lang="ts">
/**
 * Modal for generating AI content for a differentiation profile
 */

import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import LoadingCards from "@schoolboxVue/components/loading/loading-cards.vue";
import locale from "@js/terminology/rosettaStone";
import type { Profile, ProfileTextKeys, AiResponseItem } from "../js/types/manageDifferentiationProfilesTypes";
import { useHeaderStore } from "./store/mdpHeaderStore";
import { useGenAiStore } from "./store/mdpGenAiStore";
import { useSaveStore } from "./store/mdpSaveStore";

const headerStore = useHeaderStore();
const genAiStore = useGenAiStore();
const saveStore = useSaveStore();
const selectedOptions = window.Vue.ref<Partial<Record<ProfileTextKeys, AiResponseItem | null>>>({});
const hasSelectedOptions = window.Vue.computed(() => {
    // don't show confirm button unless something selected
    return Object.values(selectedOptions.value).some((item) => item !== null);
});

/**
 * Watch for changes to aiResponse and auto-select all options when it's regenerated
 */
window.Vue.watch(
    () => genAiStore.aiResponse,
    (newResponse) => {
        if (newResponse && Array.isArray(newResponse)) {
            const newSelectedOptions: Partial<Record<ProfileTextKeys, AiResponseItem | null>> = {};
            newResponse.forEach((item: AiResponseItem) => {
                // filter out those that have an empty string as a response
                // as they shouldn't be selectable
                if (item.response !== null && item.response !== "") {
                    newSelectedOptions[item.key] = item;
                }
            });
            selectedOptions.value = newSelectedOptions;
        } else if (newResponse === null) {
            selectedOptions.value = {};
        }
    },
);

/**
 * To confirm adding AI content to the differentiation profile
 */
function onConfirmSelected(): void {
    if (genAiStore.isProcessing || !genAiStore.profile || !genAiStore.aiResponse) {
        return;
    }
    // Build the toSave object with selected AI content
    const toSave: Partial<Pick<Profile, ProfileTextKeys>> = {};
    Object.values(selectedOptions.value).forEach((item: AiResponseItem | null) => {
        if (item && item.response) {
            toSave[item.key] = item.response;
        }
    });
    void saveStore.save(genAiStore.profile as Profile, toSave);
    genAiStore.cancel();
}
</script>

<template>
    <basic-modal v-model:is-open="genAiStore.isOpen">
        <form class="l-flex-col l-gap-m f-no-margins" @submit.prevent="onConfirmSelected">
            <h2>
                Suggested {{ locale.differentiationProfile.translate(false, true) }} for
                {{ genAiStore.profile?.student.givenName }}
                {{ genAiStore.profile?.student.lastName }}
            </h2>
            <request-error v-if="genAiStore.loadError" :further-info="genAiStore.loadError" />
            <div v-else-if="genAiStore.isProcessing">
                <template v-for="field in headerStore.enabledTextFields" :key="field.id">
                    <loading-cards :rows="1" :show-image="false">
                        <span class="title-placeholder"></span>
                        <p></p>
                        <p></p>
                    </loading-cards>
                </template>
            </div>
            <div v-else-if="genAiStore.aiResponse" class="sbx-vert-scroll-container l-flex-col l-gap-s">
                <div v-for="item in genAiStore.aiResponse" :key="item.key" class="l-flex-col l-gap-s">
                    <div>
                        <input
                            v-if="item.response !== null && item.response !== ''"
                            :id="`ai-response-${item.key}`"
                            v-model="selectedOptions[item.key]"
                            :true-value="item"
                            :false-value="null"
                            type="checkbox"
                            :data-test="`mdp-ai-modal-i-response-${item.key}`"
                        />
                        <label
                            :for="`ai-response-${item.key}`"
                            class="checkbox-label"
                            :data-test="`mdp-ai-modal-i-response-${item.key}-label`"
                            ><strong>{{ item.title }}</strong></label
                        >
                    </div>
                    <!-- eslint-disable-next-line vue/no-v-html - This is sanitized by the backend already -->
                    <div v-if="item.response !== null && item.response !== ''" v-html="item.response"></div>
                    <p v-else class="meta">No response provided.</p>
                </div>
                <span class="meta">AI sometimes gets things wrong — please check important details.</span>
            </div>
            <div class="l-flex l-gap-xs f-no-margins">
                <button
                    class="button l-flex__item--1"
                    data-test="up-delete-units-cancel"
                    @click.prevent="genAiStore.cancel()"
                >
                    Cancel
                </button>
                <button
                    v-if="!genAiStore.isProcessing && genAiStore.aiResponse && hasSelectedOptions"
                    class="button submit l-flex__item--1"
                    type="button"
                    data-test="up-delete-unit-delete"
                    @click.prevent="onConfirmSelected"
                >
                    Add to {{ locale.differentiationProfile.translate(false, true) }}
                </button>
            </div>
        </form>
    </basic-modal>
</template>
