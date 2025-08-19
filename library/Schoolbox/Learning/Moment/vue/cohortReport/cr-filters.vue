<script lang="ts" setup>
import { keysOf } from "@js/util/arrayUtils";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import { evidenceStatusLabels } from "@schoolboxLearningMomentEvidence/evidenceTypes";
import type { EvidenceStatus } from "@schoolboxLearningMomentEvidence/evidenceTypes";
import { useCohortReportStore } from "./store/cohortReportStore";

interface Props {
    isSingleColumn: boolean;
}

const props = defineProps<Props>();
const crStore = useCohortReportStore();

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
    // TODO (perhaps split this into a crStore.applyFilters() and keep track of the current filters so that subsequent page loads still work)
    crStore.clearData();
    await crStore.loadNextPage();
}

const studentFilter = window.Vue.ref<SearchSelectItem[]>([]);
const authorFilter = window.Vue.ref<SearchSelectItem[]>([]);
const stateFilter = window.Vue.ref<EvidenceStatus[]>(keysOf(evidenceStatusLabels));
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
                        label="Filter by student(s)"
                        label-id="students"
                        endpoint="/ajax/user.php?roleTypeID=student&"
                        :is-single-select="false"
                        placeholder="Select student(s)"
                    />
                    <search-select
                        v-model:selected="authorFilter"
                        label="Filter by author(s)"
                        label-id="author"
                        endpoint="/ajax/user.php?groupID=0&"
                        :is-single-select="false"
                        placeholder="Select author(s)"
                    />
                    <!-- TODO: filter by source -->
                    <!-- TODO: filter by start/end dates -->
                    <!-- TODO: filter by curric codes -->
                    <!-- TODO: filter by tags -->

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
</style>
