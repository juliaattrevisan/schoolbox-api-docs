<script lang="ts" setup>
import { keysOf } from "@js/util/arrayUtils";
import { apiRequest } from "@js/util/sbx/apiUtils";
// import locale from "@js/terminology/rosettaStone";
import type { SelectizeSelectorItem } from "@js/vendors/selectize/selectizeSelectorTypes";
import DateRange from "@schoolboxVue/components/dateTimePicker/date-range.vue";
import { EMPTY_DATE } from "@schoolboxVue/components/dateTimePicker/datePickerTypes";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import { evidenceStatusLabels } from "@schoolboxLearningMomentEvidence/evidenceTypes";
import type { EvidenceStatus } from "@schoolboxLearningMomentEvidence/evidenceTypes";
import { useCohortReportStore } from "./store/cohortReportStore";

interface Props {
    endpointGetEvidenceTags: string;
    isSingleColumn: boolean;
}

const props = defineProps<Props>();
const crStore = useCohortReportStore();

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

    // TODO (perhaps split this into a crStore.applyFilters() and keep track of the current filters so that subsequent page loads still work)
    crStore.clearData();
    await crStore.loadNextPage();
}

const studentFilter = window.Vue.ref<SearchSelectItem[]>([]);
const authorFilter = window.Vue.ref<SearchSelectItem[]>([]);
const stateFilter = window.Vue.ref<EvidenceStatus[]>(keysOf(evidenceStatusLabels));
const evidenceTagsFilter = window.Vue.ref<SelectizeSelectorItem["id"][]>([]);
const startDateFilter = window.Vue.ref<string>(EMPTY_DATE);
const endDateFilter = window.Vue.ref<string>(EMPTY_DATE);

const tagsSelectLoaded = window.Vue.ref(false);

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
            onChange(value: string[]): void {
                evidenceTagsFilter.value = value.map((v) => parseInt(v, 10));
            },
        },
        value: [],
        multiple: true,
    });
});
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
                        v-model:selected="studentFilter"
                        label="Filter by students"
                        label-id="students"
                        endpoint="/ajax/user.php?roleTypeID=student&"
                        :is-single-select="false"
                        placeholder="Search for students..."
                    />

                    <search-select
                        v-model:selected="authorFilter"
                        label="Filter by authors"
                        label-id="author"
                        endpoint="/ajax/user.php?groupID=0&"
                        :is-single-select="false"
                        placeholder="Search for users..."
                    />

                    <!-- TODO: filter by classes/groups [SA-20735] -->
                    <!-- <label> Filter by {{ locale.class.properPlural() }} and Groups </label> -->

                    <date-range
                        ref="dateRangeRef"
                        v-model:start-date="startDateFilter"
                        v-model:end-date="endDateFilter"
                        id-prefix="cr-filters"
                    />

                    <!-- TODO: filter by curric codes [SA-20736] -->
                    <!-- TODO: filter by tags -->

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
                                    v-model="stateFilter"
                                    type="checkbox"
                                    :value="status"
                                />
                                <label :for="`cr-filters-${status}`" class="checkbox-label">
                                    {{ label }}
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="l-flex-row">
                        <button
                            class="button submit l-flex__item--1 f-no-margins"
                            data-test="cr-filter-clear-button"
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
