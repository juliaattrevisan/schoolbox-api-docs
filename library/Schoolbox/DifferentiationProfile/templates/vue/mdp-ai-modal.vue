<script setup lang="ts">
/**
 * Modal for generating AI content for a differentiation profile
 */

import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import LoadingCards from "@schoolboxVue/components/loading/loading-cards.vue";
import locale from "@js/terminology/rosettaStone";
import type { Profile, ProfileTextKeys } from "../js/types/manageDifferentiationProfilesTypes";
import { useHeaderStore } from "./store/mdpHeaderStore";
import { useGenAiStore } from "./store/mdpGenAiStore";
import { useSaveStore } from "./store/mdpSaveStore";

const headerStore = useHeaderStore();
const genAiStore = useGenAiStore();
const saveStore = useSaveStore();
const selectedOptions = window.Vue.ref<string[]>([]);

/**
 * Watch for changes to aiResponse and auto-select all options when it's regenerated
 */
window.Vue.watch(
    () => genAiStore.aiResponse,
    (newResponse) => {
        if (newResponse && Array.isArray(newResponse)) {
            selectedOptions.value = newResponse.map((item) => item.key as string);
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

    // Update the profile with selected AI content
    const { profile } = genAiStore;
    const profileToUpdate = profile as Profile;

    selectedOptions.value.forEach((selectedKey) => {
        const aiResponseItem = genAiStore.aiResponse?.find((item: any) => item.key === selectedKey) as any;
        if (aiResponseItem && aiResponseItem.response) {
            profileToUpdate[selectedKey as ProfileTextKeys] = aiResponseItem.response;
        }
    });

    // Save the updated profile
    void saveStore.save(profileToUpdate);

    // Close the modal
    genAiStore.cancel();
}
</script>

<template>
    <basic-modal v-model:is-open="genAiStore.isOpen">
        <form class="l-flex-col l-gap-m f-no-margins" @submit.prevent="onConfirmSelected">
            <h2 data-test="up-delete-units-title">
                Suggested {{ locale.differentiationProfile.translate(false, true) }} for
                {{ genAiStore.profile?.student.givenName }}
                {{ genAiStore.profile?.student.lastName }}
            </h2>
            <request-error v-if="genAiStore.loadError" :further-info="genAiStore.loadError" />
            <div v-else-if="genAiStore.isProcessing">
                <template v-for="(field, i) in headerStore.enabledFields" :key="field.id">
                    <hr v-if="i > 0" />
                    <loading-cards :rows="1" :show-image="false">
                        <h3>&nbsp;</h3>
                        <p></p>
                        <p></p>
                        <p></p>
                    </loading-cards>
                </template>
            </div>
            <div v-else-if="genAiStore.aiResponse">
                <template v-for="(item, i) in genAiStore.aiResponse" :key="item.key">
                    <hr v-if="i > 0" />
                    <div class="l-flex-col l-gap-s">
                        <div>
                            <input
                                :id="`ai-response-${item.key}`"
                                v-model="selectedOptions"
                                :value="item.key"
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
                        <div v-html="item.response"></div>
                    </div>
                </template>
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
                    v-if="!genAiStore.isProcessing"
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
