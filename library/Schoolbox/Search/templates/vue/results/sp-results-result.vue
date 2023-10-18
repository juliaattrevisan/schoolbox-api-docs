<script setup lang="ts">
/**
 * Display a single search result
 */

import { gsap } from "gsap/all";
import { Result } from "../../js/types/Result";
import SpResultsResultImage from "./sp-results-result-image.vue";
import SpResultsResultTitle from "./sp-results-result-title.vue";
import SpResultsResultMeta from "./sp-results-result-meta.vue";
import SpResultsResultDescription from "./sp-results-result-description.vue";
import SpResultsSubResults from "./sp-results-sub-results.vue";

interface Props {
    result: Result; // the result to display
    index: number; // the index of the result
}
const props = defineProps<Props>();

const resultEl = window.Vue.ref<HTMLDivElement | null>(null);

/**
 * To display the result, stagger to fade on each sequentially
 */
window.Vue.onMounted(() => {
    gsap.from(resultEl.value, {
        delay: props.index * 0.02,
        duration: 0.2,
        opacity: 0,
    });
});

/**
 * Whether the result has sub results
 */
const hasSubResults = window.Vue.computed(() => {
    return props.result._subResults && props.result._subResults.data.length > 0;
});
</script>

<template>
    <div ref="resultEl" class="l-flex-col">
        <div
            :class="`c-card ${hasSubResults ? 'c-card--border-end' : ''}`"
            :data-test="`sp-results-result-${props.result.id}`"
        >
            <div class="l-flex-col l-gap-m l-pad-m">
                <div class="l-flex-row l-gap-m">
                    <sp-results-result-image :result="props.result" />
                    <div class="l-flex-col l-flex--justify-center l-flex__item--1 f-no-margins">
                        <sp-results-result-title :result="props.result" />
                        <sp-results-result-meta :result="props.result" />
                    </div>
                </div>
                <sp-results-result-description v-if="props.result.description" :result="result" />
            </div>
        </div>
        <sp-results-sub-results v-if="hasSubResults" :result="result" />
    </div>
</template>

<style scoped></style>
