<script setup lang="ts">
/**
 * Selectize input for selecting learning moment evidence tags
 */

import { apiRequest } from "@js/util/sbx/apiUtils";
import type { SelectizeSelectorItem } from "@js/vendors/selectize/selectizeSelectorTypes";

interface Props {
    endpointGetEvidenceTags: string;
}

const props = defineProps<Props>();

const selectedTags = defineModel<SelectizeSelectorItem[] | null>("tags", { required: true });
const evidenceTagsEl = window.Vue.useTemplateRef<HTMLSelectElement>("evidenceTagsRef");

const tagsSelectLoaded = window.Vue.ref(false);
const isChangingTags = window.Vue.ref(false);

/**
 * Initialise the selectize for evidence tags
 */
window.Vue.onMounted(async () => {
    // We only need to fetch this once, it won't change and there's no filtering
    const response = await apiRequest<SelectizeSelectorItem[]>({
        url: props.endpointGetEvidenceTags,
        method: "GET",
    });

    if (!response.success) {
        console.warn("Failed to load evidence tags filter", response);
        return;
    }

    // only show the real select once we're ready to initialise it
    tagsSelectLoaded.value = true;
    await window.Vue.nextTick();
    if (!evidenceTagsEl.value) {
        throw new Error("Evidence tags select element not found");
    }

    const evidenceTags = response.data;
    $(evidenceTagsEl.value).selectizeSelector({
        selectize: {
            placeholder: "Select tags",
            preload: true,
            persist: true,
            hideSelected: true,
            load: (query: string, callback: (results: SelectizeSelectorItem[]) => void): void => {
                callback(evidenceTags);
            },
            async onChange(value: string[]): Promise<void> {
                isChangingTags.value = true;
                selectedTags.value = value.map((v) => {
                    const tag = evidenceTags.find((et) => et.id.toString() === v);

                    if (!tag) {
                        throw new Error(`Selected tag with ID ${v} not found`);
                    }

                    return tag;
                });

                await window.Vue.nextTick();
                isChangingTags.value = false;
            },
        },
        value: selectedTags.value ?? [],
        multiple: true,
    });
});

/**
 * Persist changes to the evidence tags back to the selectize
 */
window.Vue.watch(
    () => selectedTags.value,
    (newValue) => {
        if (!evidenceTagsEl.value) return;
        if (isChangingTags.value) return; // internal change, no need to update

        if (newValue === null) {
            evidenceTagsEl.value.selectize.clear(true);
            return;
        }

        evidenceTagsEl.value.selectize.setValue(
            newValue.map((tag) => tag.id),
            true,
        );
    },
);
</script>

<template>
    <input v-if="!tagsSelectLoaded" type="text" class="search-input f-no-margins" placeholder="Select tags" disabled />
    <select v-else ref="evidenceTagsRef" name="tags[]" multiple></select>
</template>

<style scoped></style>
