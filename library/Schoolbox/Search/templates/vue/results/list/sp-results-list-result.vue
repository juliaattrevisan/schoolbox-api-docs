<script setup lang="ts">
/**
 * Display a single search result
 */

import { addMarks } from "@js/util/domUtils";
import { Result } from "../../../js/types/Result";
import { useQueryStore } from "../../store/spQueryStore";
import SpResultsListSubResults from "./sp-results-list-sub-results.vue";

interface Props {
    result: Result; // the result to display
}
const props = defineProps<Props>();

const queryStore = useQueryStore();

/**
 * Whether the result has sub results
 */
const hasSubResults = window.Vue.computed(() => {
    return props.result._subResults && props.result._subResults.data.length > 0;
});
</script>

<template>
    <div class="sb-card" data-test-sp-results-list-result>
        <div class="sb-card__main">
            <div class="sb-card__primary-img img img--round">
                <div class="img__wrapper">
                    <img :src="(props.result.image as string)" />
                </div>
            </div>
            <div class="sb-card__main-content">
                <a :href="props.result.url">
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <h3 v-html="addMarks(props.result.title, queryStore.queryWordList)"></h3
                ></a>
                <span v-if="props.result.meta" class="meta">{{ props.result.meta }}</span>
            </div>
        </div>
    </div>
    <!-- <div class="result" data-test-sp-results-list-result>
        <h2>{{ props.result.title }}</h2>
        <p class="meta">{{ props.result.meta }}</p>
        <sp-results-list-sub-results v-if="hasSubResults" :result="result" />
    </div> -->
</template>

<style scoped>
/* .result {
    background: white;
    padding: 1rem;
} */
</style>
