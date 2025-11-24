<script setup lang="ts">
/**
 * Modal for generating AI content for a differentiation profile
 */

import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import locale from "@js/terminology/rosettaStone";
import { useGenAiStore } from "./store/mdpGenAiStore";

const genAiStore = useGenAiStore();

/**
 * To confirm adding AI content to the differentiation profile
 */
function onConfirmSelected(): void {
    if (genAiStore.isProcessing) {
        return;
    }
    // void genAiStore.generate(genAiStore.profileToGenerate);
}
</script>

<template>
    <basic-modal v-model:is-open="genAiStore.isOpen">
        <form class="l-flex-col l-gap-s f-no-margins" @submit.prevent="onConfirmSelected">
            <h2 data-test="up-delete-units-title">
                Suggested {{ locale.differentiationProfile.translate(false, true) }} for
                {{ genAiStore.profile?.student.givenName }}
                {{ genAiStore.profile?.student.lastName }}
            </h2>
            <request-error v-if="genAiStore.loadError" :further-info="genAiStore.loadError" />
            <div class="l-flex l-gap-xs f-no-margins">
                <button
                    class="button l-flex__item--1"
                    data-test="up-delete-units-cancel"
                    @click.prevent="genAiStore.cancel()"
                >
                    Cancel
                </button>
                <button
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
