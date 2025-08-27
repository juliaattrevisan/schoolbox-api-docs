<script lang="ts" setup>
import { apiRequest } from "@js/util/sbx/apiUtils";
import locale from "@js/terminology/rosettaStone";
import type { SelectizeSelectorItem } from "@js/vendors/selectize/selectizeSelectorTypes";
import DateRange from "@schoolboxVue/components/dateTimePicker/date-range.vue";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import { evidenceStatusLabels } from "@schoolboxLearningMomentEvidence/evidenceTypes";
import { useCohortReportFiltersStore } from "./store/cohortReportFiltersStore";

interface Props {
    endpointGetEvidenceTags: string;
    isSingleColumn: boolean;
}

const props = defineProps<Props>();
const crfStore = useCohortReportFiltersStore();

const dateRangeEl = window.Vue.useTemplateRef<InstanceType<typeof DateRange>>("dateRangeRef");
const evidenceTagsEl = window.Vue.useTemplateRef<HTMLSelectElement>("evidenceTagsRef");

const isOpen = window.Vue.ref(true); // whether the filters are shown

/**
 * By default hide the filters when on single column display
 */
window.Vue.onMounted(() => {
    isOpen.value = !props.isSingleColumn;
});

/**
 * Track the column state, to ensure the filters are shown when the page is resizied to above single column
 * and hidden when resized to single column
 */
window.Vue.watch(
    () => props.isSingleColumn,
    (newValue, oldValue) => {
        if (newValue !== oldValue && newValue === false) {
            isOpen.value = true;
            return;
        }
        if (newValue !== oldValue && newValue === true) {
            isOpen.value = false;
        }
    },
);

/**
 * To apply the selected filters
 */
async function applyFilters(): Promise<void> {
    // clear the invalid date inputs (if any) to correctly reflect what filters are being applied
    dateRangeEl.value?.clearInvalidDates();

    await crfStore.applyFilters();
}

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
                crfStore.evidenceTagsFilter = value.map((v) => parseInt(v, 10));

                await window.Vue.nextTick();
                isChangingTags.value = false;
            },
        },
        value: [],
        multiple: true,
    });
});

/**
 * Persist changes to the evidence tags back to the selectize
 */
window.Vue.watch(
    () => crfStore.evidenceTagsFilter,
    (newValue) => {
        if (!evidenceTagsEl.value) return;
        if (isChangingTags.value) return; // internal change, no need to update

        evidenceTagsEl.value.selectize.setValue(newValue, true);
    },
);
</script>

<template>
    <form class="l-flex-col l-gap-s l-flex__item--0-shrink f-no-margins" @submit.prevent="applyFilters">
        <div class="c-island filters l-flex-col l-pad-m" :class="{ 'l-gap-m': isOpen }">
            <button
                v-if="props.isSingleColumn"
                class="filters__toggle-button l-mar--0"
                :class="isOpen ? 'icon-up-arrow' : 'icon-down-arrow'"
                @click.prevent="isOpen = !isOpen"
            >
                {{ isOpen ? "Hide filters" : "Show filters" }}
            </button>
            <div class="filters__container" :style="{ 'grid-template-rows': isOpen ? '1fr' : '0fr' }">
                <div class="filters__inner-container l-flex-col l-gap-m">
                    <search-select
                        v-model:selected="crfStore.studentFilter"
                        label="Filter by students"
                        label-id="students"
                        endpoint="/ajax/user.php?roleTypeID=student&"
                        :is-single-select="false"
                        placeholder="Search for students..."
                    />

                    <search-select
                        v-model:selected="crfStore.authorFilter"
                        label="Filter by authors"
                        label-id="author"
                        endpoint="/ajax/user.php?groupID=0&"
                        :is-single-select="false"
                        placeholder="Search for users..."
                    />

                    <search-select
                        v-model:selected="crfStore.folderFilter"
                        :label="`Filter by ${locale.class.plural()} and groups`"
                        label-id="folder"
                        endpoint="/learning/evidence/folders?"
                        :is-single-select="false"
                        placeholder="Search for classes and groups..."
                    />

                    <date-range
                        ref="dateRangeRef"
                        v-model:start-date="crfStore.startDateFilter"
                        v-model:end-date="crfStore.endDateFilter"
                        id-prefix="cr-filters"
                    />

                    <!-- TODO: filter by curric codes [SA-20736] -->

                    <label class="cr-selectize-label">
                        Filter by tags
                        <input
                            v-if="!tagsSelectLoaded"
                            type="text"
                            class="search-input f-no-margins"
                            placeholder="Select tags"
                            disabled
                        />
                        <select v-else ref="evidenceTagsRef" name="tags[]" multiple></select>
                    </label>

                    <div>
                        <label>Filter by state</label>
                        <div class="l-grid l-gap-s l-grid--2-col">
                            <div v-for="(label, status) in evidenceStatusLabels" :key="status">
                                <input
                                    :id="`cr-filters-${status}`"
                                    v-model="crfStore.stateFilter"
                                    type="checkbox"
                                    :value="status"
                                />
                                <label :for="`cr-filters-${status}`" class="checkbox-label">
                                    {{ label }}
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="l-flex-row l-flex--no-wrap l-gap-m">
                        <button
                            class="button l-flex__item--1 icon-close f-no-margins"
                            data-test="cr-filter-clear-button"
                            @click.prevent="crfStore.resetFilters"
                        >
                            Clear filters
                        </button>
                        <button
                            class="button submit l-flex__item--1 icon-search f-no-margins"
                            data-test="cr-filter-apply-button"
                            @click.prevent="applyFilters"
                        >
                            Apply filters
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</template>

<style lang="scss" scoped>
.filters {
    &__container {
        display: grid;
        transition: grid-template-rows 300ms;
    }

    &__inner-container {
        overflow: hidden;
    }

    &__toggle-button {
        background: none;
        color: var(--content-ui-foreground);
        font-weight: bold;
        padding: var(--spacing-xs) 0;
        text-align: left;
    }
}

// We're controlling margins here, so override it from the selectize plugin styles
.cr-selectize-label > :deep(.selectize-control) {
    margin-bottom: 0;
}
</style>
