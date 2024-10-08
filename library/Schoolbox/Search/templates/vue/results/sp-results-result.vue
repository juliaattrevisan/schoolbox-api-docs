<script setup lang="ts">
/**
 * Display a single search result
 */

import type { Badge } from "@schoolboxBadge/templates/types/Badge";
import type { Result } from "../../js/types/Result";
import SpResultsResultImage from "./sp-results-result-image.vue";
import SpResultsResultTitle from "./sp-results-result-title.vue";
import SpResultsResultMeta from "./sp-results-result-meta.vue";
import SpResultsResultActions from "./sp-results-result-actions.vue";
import SpResultsResultDescription from "./sp-results-result-description.vue";
import SpResultsSubResults from "./sp-results-sub-results.vue";
import { useBadgeStore } from "./store/spBadgeStore";

interface Props {
    result: Result; // the result to display
    index: number; // the index of the result
}
const props = defineProps<Props>();

const resultEl = window.Vue.ref<HTMLDivElement | null>(null);
const badgeStore = useBadgeStore();

/**
 * Whether the result has sub results
 */
const hasSubResults = window.Vue.computed(() => {
    return props.result._subResults && props.result._subResults.data.length > 0;
});

/**
 * When a badge is selected, set it in the store which will prompt the
 * badge modal to open
 */
async function onBadgeSelected(): Promise<void> {
    const getBadge = await fetch(`learning/badge/${props.result.id}`);
    const badge = (await getBadge.json()) as Badge;
    badgeStore.openDetailModal(badge);
}
</script>

<template>
    <div ref="resultEl" class="l-flex-col" :data-test="`sp-results-result-${props.result.id}`">
        <div :class="`c-card ${hasSubResults ? 'c-card--border-end' : ''}`">
            <div class="l-flex-col l-gap-m l-pad-m">
                <div class="l-flex-row l-gap-m">
                    <sp-results-result-image
                        v-if="props.result.image || props.result.icon"
                        :result="props.result"
                        @badge-selected="onBadgeSelected"
                    />
                    <div class="l-flex-col l-flex--justify-center l-flex__item--1 f-no-margins">
                        <sp-results-result-title :result="props.result" @badge-selected="onBadgeSelected" />
                        <sp-results-result-meta :result="props.result" />
                    </div>
                    <sp-results-result-actions v-if="props.result._links.length > 0" :result="props.result" />
                </div>
                <sp-results-result-description v-if="props.result.description" :result="result" />
            </div>
        </div>
        <sp-results-sub-results v-if="hasSubResults" :result="result" :parent-index="props.index" />
    </div>
</template>

<style scoped></style>
