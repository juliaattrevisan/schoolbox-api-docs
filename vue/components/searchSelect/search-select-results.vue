<script setup lang="ts">
/**
 * The search results container
 */

import gsap from "gsap";
import type { Result } from "./types/Result";
import SearchSelectSelectAll from "./search-select-select-all.vue";
import SearchSelectResult from "./search-select-result.vue";
import { useSearchStore } from "./store/searchSelectStore";

interface Props {
    isSingleSelect: boolean; // whether only one result can be selected
    selected: Result[]; // the selected results
}

const props = defineProps<Props>();

const emit = defineEmits<{
    select: [result: Result];
}>();

const store = useSearchStore();
const searchResultsContainerEl = window.Vue.ref<HTMLDivElement | null>(null);
const searchResultsEl = window.Vue.ref<HTMLDivElement | null>(null);

/**
 * to animate the height of the search results container
 * @param targetHeight - the height to animate to
 */
function animateHeight(targetHeight: number): void {
    gsap.to(searchResultsContainerEl.value, {
        duration: 0.3,
        height: targetHeight === 1 ? 0 : targetHeight,
    });
}

/**
 * to animate the search results
 */
async function animateResults(): Promise<void> {
    await window.Vue.nextTick();
    const targetHeight = (searchResultsEl.value as HTMLDivElement).offsetHeight;
    animateHeight(targetHeight);
}

/**
 * results have been preset prior to search
 */
window.Vue.onMounted(async () => {
    if (store.currentResults.length > 0) {
        await animateResults();
    }
});

/**
 * to slide up and down the search results
 */
window.Vue.watch(
    () => store.isActive,
    async () => {
        await animateResults();
    },
);

/**
 * to slide up and down the search results
 */
window.Vue.watch(
    () => store.currentResults,
    async (newResults, oldResults) => {
        if (newResults.length === oldResults.length) {
            return;
        }
        await animateResults();
    },
);

/**
 * show select all option for adding / removing results only if there's
 * more than one search result and only a single result can't be selected
 * @returns {boolean} - whether to show the select all option
 */
const showSelectAll = window.Vue.computed(() => {
    return !props.isSingleSelect && store.currentResults.length <= 1;
});

/**
 * To toggle the selected state of the result
 * @param result - the result selected
 */
function onSelected(result: Result): void {
    emit("select", result);
}
</script>
<template>
    <div ref="searchResultsContainerEl" class="search-results-container">
        <div ref="searchResultsEl" class="search-results">
            <div v-if="store.isLoading" class="search-status">Searching for results....</div>
            <div v-else-if="store.hasNoResults && props.selected.length === 0" class="search-status">
                No results found for your current search.
            </div>
            <template v-else>
                <search-select-select-all v-if="showSelectAll" />
                <search-select-result
                    v-for="result in store.currentResults"
                    :key="result.id"
                    :data-test="`search-select-result-${result.id}`"
                    :selected="selected"
                    :results="store.currentResults"
                    :result="result"
                    :is-single-select="props.isSingleSelect"
                    @select="onSelected"
                />
            </template>
        </div>
        <!-- .search-results -->
    </div>
    <!-- .search-results-container -->
</template>

<style lang="scss" scoped>
.search-results-container {
    height: 0; //will animate the results within with the window.Vue.watch() above
    overflow: hidden;
    position: relative;
}

.search-results {
    max-height: 400px;
    overflow: auto;
    width: 100%;
    &:last-child {
        border-bottom: 1px solid var(--form-border-color);
    }
}

.search-status {
    background: var(--color-foreground-primary);
    border-left: 1px solid var(--form-border-color);
    border-right: 1px solid var(--form-border-color);
    padding: var(--spacing-s);
    font-size: 0.825rem;
    font-style: italic;
}
</style>
