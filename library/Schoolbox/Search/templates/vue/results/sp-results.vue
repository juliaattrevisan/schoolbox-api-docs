<script setup lang="ts">
/**
 * To display the list of returned results
 */

import { useResultsStore } from "./store/spResultsStore";
import SpResultsResult from "./sp-results-result.vue";
import SpResultsLoadMore from "./sp-results-load-more.vue";
import SpBadgeAward from "./sp-badge-award.vue";

const resultsStore = useResultsStore();
</script>

<template>
    <div class="l-flex-col l-gap-m">
        <!-- using index as the key is typically not advised, however as the results will never change their order, it's fine in this instance -->
        <sp-results-result
            v-for="(result, index) in resultsStore.allResults"
            :key="index"
            :result="result"
            :index="index"
        />
        <sp-results-load-more />
    </div>
    <sp-badge-award />
</template>

<style scoped></style>


<div class="l-flex-col" data-test="sp-results-result"><div class="c-card" data-test="sp-results-result-1"><div class="l-flex-col l-gap-m l-pad-m"><div class="l-flex-row l-gap-m"><!--v-if--><div class="l-flex-col l-flex--justify-center l-flex__item--1 f-no-margins"><!-- eslint-disable-next-line vue/no-v-html --><h3 /><span class="meta" /></div><!--v-if--></div><!--v-if--></div></div><!--v-if--></div>}, {"isDisabled": [Function anonymous], "wrapperElement": <div class="c-card" data-test="sp-results-result-1"><div class="l-flex-col l-gap-m l-pad-m"><div class="l-flex-row l-gap-m"><!--v-if--><div class="l-flex-col l-flex--justify-center l-flex__item--1 f-no-margins"><!-- eslint-disable-next-line vue/no-v-html --><h3 /><span class="meta" /></div><!--v-if--></div><!--v-if--></div></div>}, {"isDisabled": [Function anonymous], "wrapperElement": <div class="l-flex-col" data-test="sp-results-result"><div class="c-card" data-test="sp-results-result-2"><div class="l-flex-col l-gap-m l-pad-m"><div class="l-flex-row l-gap-m"><!--v-if--><div class="l-flex-col l-flex--justify-center l-flex__item--1 f-no-margins"><!-- eslint-disable-next-line vue/no-v-html --><h3 /><span class="meta" /></div><!--v-if--></div><!--v-if--></div></div><!--v-if--></div>}, {"isDisabled": [Function anonymous], "wrapperElement": <div class="c-card" data-test="sp-results-result-2"><div class="l-flex-col l-gap-m l-pad-m"><div class="l-flex-row l-gap-m"><!--v-if--><div class="l-flex-col l-flex--justify-center l-flex__item--1 f-no-margins"><!-- eslint-disable-next-line vue/no-v-html --><h3 /><span class="meta" /></div><!--v-if--></div><!--v-if--></div></div>
