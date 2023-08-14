<script setup lang="ts">
/**
 * Display a single search result
 */

import { addMarks } from "@js/util/domUtils";
import { Result } from "../../../js/types/Result";
import { ResultTypes } from "../../../js/types/ResultTypes";
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

/**
 * Whether to use a rounded image
 */
const isImgRounded = window.Vue.computed(() => {
    if (props.result.type.includes(ResultTypes.USER)) {
        return true;
    }
    return false;
});

/**
 *
 */
const relativeTime = window.Vue.computed(() => {
    return window.dayjs.duration(window.dayjs(props.result.date).diff()).humanize(true);
});
</script>

<template>
    <div class="c-card" data-test-sp-results-list-result>
        <div class="l-flex-row l-flex--center-align l-gap-m l-pad-xs--vert">
            <div :class="` c-img c-img--s ${isImgRounded ? 'c-img--round' : ''}`">
                <div class="c-img__wrapper">
                    <div v-if="props.result.type === ResultTypes.NEWS" class="icon icon-news"></div>
                    <img v-else :src="(props.result.image as string)" :alt="`Photo of ${props.result.title}`" />
                </div>
            </div>
            <div class="l-flex-col l-flex--center-justify f-no-margins">
                <h3>
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <a :href="props.result.url" v-html="addMarks(props.result.title, queryStore.queryWordList)"></a>
                </h3>
                <span v-if="props.result.type === ResultTypes.NEWS" class="meta"
                    >Posted by
                    <a :href="props.result.creator?._links?.profile">{{ props.result.creator?.fullName }}</a>
                    {{ relativeTime }}</span
                >
                <span v-else-if="props.result.meta" class="meta">{{ props.result.meta }}</span>
            </div>
        </div>
        <div v-if="props.result.type === ResultTypes.NEWS" class="l-pad-s">
            <article>
                <!-- eslint-disable-next-line vue/no-v-html -->
                <p v-html="addMarks(props.result.description as string, queryStore.queryWordList)"></p>
            </article>
        </div>
        <sp-results-list-sub-results v-if="hasSubResults" :result="result" />
    </div>
</template>

<style scoped></style>
