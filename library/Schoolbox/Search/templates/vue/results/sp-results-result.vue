<script setup lang="ts">
/**
 * Display a single search result
 */

import { gsap } from "gsap/all";
import { addMarks } from "@js/util/domUtils";
import { Result } from "../../js/types/Result";
import { ResultTypes } from "../../js/types/ResultTypes";
import { useQueryStore } from "../store/spQueryStore";
import SpResultsResultImage from "./sp-results-result-image.vue";
import SpResultsResultDescription from "./sp-results-result-description.vue";
import SpResultsSubResults from "./sp-results-sub-results.vue";

interface Props {
    result: Result; // the result to display
    index: number; // the index of the result
}
const props = defineProps<Props>();

const resultEl = window.Vue.ref<HTMLDivElement | null>(null);
const queryStore = useQueryStore();

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

/**
 * To display the date in format
 * @returns {string} the formatted date
 */
const dateFormatted = window.Vue.computed(() => {
    return window.dayjs(props.result.date).format("DD/MM/YYYY");
});
</script>

<template>
    <div ref="resultEl" class="l-flex-col">
        <div :class="`c-card ${hasSubResults ? 'c-card--border-end' : ''}`" data-test-sp-results-result>
            <div class="l-flex-col l-gap-m l-pad-m">
                <div class="l-flex-row l-gap-m">
                    <sp-results-result-image :result="props.result" />
                    <div class="l-flex-col l-flex--justify-center l-flex__item--1 f-no-margins">
                        <h3>
                            <!-- eslint-disable-next-line vue/no-v-html -->
                            <a
                                :href="props.result.url"
                                v-html="addMarks(props.result.title, queryStore.queryWordList)"
                            ></a>
                        </h3>
                        <span v-if="props.result.type === ResultTypes.NEWS" class="meta"
                            >Posted by
                            <a :href="props.result.creator?._links?.profile">{{ props.result.creator?.fullName }}</a> on
                            {{ dateFormatted }}</span
                        >
                        <span v-else-if="props.result.meta" class="meta">{{ props.result.meta }}</span>
                        <span v-else-if="props.result.context" class="meta"
                            ><template v-for="(context, index) in props.result.context" :key="context.id"
                                ><a :href="context._links.view">{{ context.title }}</a
                                ><template v-if="index < props.result.context.length - 1"> > </template>
                            </template></span
                        >
                    </div>
                </div>
                <sp-results-result-description v-if="props.result.description" :result="result" />
            </div>
        </div>
        <sp-results-sub-results v-if="hasSubResults" :result="result" />
    </div>
</template>

<style scoped></style>
