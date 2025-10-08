<script lang="ts" setup>
import locale from "@js/terminology/rosettaStone";
import DateRange from "@schoolboxVue/components/dateTimePicker/date-range.vue";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import EvidenceTags from "@schoolboxLearningMomentEvidence/evidence-tags.vue";
import { evidenceStatusLabels } from "@schoolboxLearningMomentEvidence/evidenceTypes";
import { useCohortReportFiltersStore } from "./store/cohortReportFiltersStore";

interface Props {
    endpointGetEvidenceTags: string;
    endpointGetCohortFolderList: string;
    isSingleColumn: boolean;
}

const props = defineProps<Props>();
const crfStore = useCohortReportFiltersStore();

const dateRangeEl = window.Vue.useTemplateRef<InstanceType<typeof DateRange>>("dateRangeRef");

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
                        :endpoint="props.endpointGetCohortFolderList"
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
                        <evidence-tags
                            v-model:tags="crfStore.evidenceTagsFilter"
                            :endpoint-get-evidence-tags="props.endpointGetEvidenceTags"
                        />
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
