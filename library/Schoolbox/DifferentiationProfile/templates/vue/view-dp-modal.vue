<script setup lang="ts">
/**
 * To display a students DP in a modal
 */

import locale from "@js/terminology/rosettaStone";
import { apiRequest } from "@js/util/sbx/apiUtils";
import { getFirstName } from "@js/util/sbx/userUtils";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import MediaArticle from "@schoolboxVue/components/media-article.vue";
import type { User } from "@schoolboxLibrary/types/User";

/**
 * The returned DP item type
 */
type Item = {
    terminology: string;
    value: string | { name: string }[] | null;
};

/**
 * Valid adjustment item with array value
 */
type AdjustmentItem = {
    terminology: string;
    value: { name: string }[];
};

/**
 * Valid text item with string value
 */
type TextItem = {
    terminology: string;
    value: string;
};

const props = defineProps<{
    dpRoute: string;
    customisationsRoute: string;
}>();

const student = defineModel<User | null>("student", { default: null });
const isOpen = window.Vue.ref(false);
const isProcessing = window.Vue.ref(false);
const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the create
const data = window.Vue.ref<Item[]>([]);
const adjustmentData = window.Vue.ref<AdjustmentItem[]>([]);
const textData = window.Vue.ref<TextItem[]>([]);

/**
 * Load the DP
 */
async function load(): Promise<void> {
    if (!props.dpRoute || props.dpRoute === "") {
        loadError.value = "No route specified";
        return;
    }
    try {
        isProcessing.value = true;
        loadError.value = null;
        const response = await apiRequest<Item[]>({
            url: props.dpRoute,
            method: "GET",
        });
        if (!response.success) {
            throw new Error(
                response.message || `Failed to retrieve ${locale.differentiationProfile.translate(false, true)}`,
            );
        }
        data.value = response.data || [];
        data.value = data.value.filter((item) => item.value !== null); // filter out items that have a null value
        adjustmentData.value = data.value.filter((item) => Array.isArray(item.value)) as AdjustmentItem[]; // adjustments are arrays
        textData.value = data.value.filter((item) => !Array.isArray(item.value)) as TextItem[]; // leaving just text items
    } catch (error) {
        const errorMessage = error instanceof Error ? error.message : "There was an unexpected error";
        loadError.value = errorMessage;
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
        <div v-else-if="student && data.length > 0" class="l-flex-col l-gap-m f-no-margins">
            <div class="l-flex-col l-gap-s f-no-margins">
                <div class="l-flex-row l-flex--justify-space">
                    <h2>
                        {{ locale.differentiationProfile.translate(false, true) }} for {{ getFirstName(student) }}
                        {{ student.lastname }}
                    </h2>
                    <a
                        v-if="props.customisationsRoute"
                        :href="`${props.customisationsRoute}`"
                        class="button l-flex l-gap-s sbx-button--has-icon"
                    >
                        <sbx-icon name="group"></sbx-icon>
                        View Customisations
                    </a>
                </div>
            </div>
            <div class="sbx-vert-scroll-container l-flex-col l-gap-s" data-test="view-dp-modal-fields-container">
                <div class="l-flex-col l-gap-s f-no-margins">
                    <div
                        v-for="item in adjustmentData"
                        :key="item.terminology"
                        class="l-flex-col l-gap-xs f-no-margins"
                    >
                        <h5>
                            <strong>{{ item.terminology }}</strong>
                        </h5>
                        <article>
                            <ul>
                                <li v-for="(value, index) in item.value" :key="index">{{ value.name }}</li>
                            </ul>
                        </article>
                    </div>
                    <div v-for="item in textData" :key="item.terminology" class="l-flex-col l-gap-xs f-no-margins">
                        <h5>
                            <strong>{{ item.terminology }}</strong>
                        </h5>
                        <media-article :content="item.value" />
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
