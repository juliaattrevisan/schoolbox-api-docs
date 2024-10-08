<script setup lang="ts">
/**
 * To display a single linked sub result within a result
 */

import type { Result } from "../../js/types/Result";
import SpResultsResultImage from "./sp-results-result-image.vue";
import SpResultsResultTitle from "./sp-results-result-title.vue";
import SpResultsResultMeta from "./sp-results-result-meta.vue";
import SpResultsResultActions from "./sp-results-result-actions.vue";
import SpResultsResultDescription from "./sp-results-result-description.vue";

interface Props {
    subResult: Result; // the result to display
    isLast: boolean; // whether this is the last result, currently to determine whether to show the border
}
const props = defineProps<Props>();
</script>

<template>
    <div :class="`c-card c-card--child ${!props.isLast ? `c-card--border-end` : ''}`">
        <div class="l-flex-col l-gap-s l-pad-m">
            <div class="l-flex-row l-gap-s">
                <sp-results-result-image :result="props.subResult" :is-sub-result="true" />
                <div class="l-flex-col l-flex--justify-center l-flex__item--1 f-no-margins">
                    <sp-results-result-title :result="props.subResult" :is-sub-result="true" />
                    <sp-results-result-meta :result="props.subResult" :is-sub-result="true" />
                </div>
                <sp-results-result-actions v-if="props.subResult._links.length > 0" :result="props.subResult" />
            </div>
            <sp-results-result-description v-if="props.subResult.description" :result="props.subResult" />
        </div>
    </div>
</template>

<style scoped></style>
