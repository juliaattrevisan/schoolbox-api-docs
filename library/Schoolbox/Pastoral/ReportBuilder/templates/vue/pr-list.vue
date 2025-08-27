<script setup lang="ts">
/**
 * To display a list of pastoral records in a list format
 */

import { toRefs } from "vue";
import { useScroll } from "@vueuse/core";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import PrRecord from "./pr-record.vue";
import PrEmpty from "./pr-empty.vue";
import { useStore } from "./store/pastoralReportStore";
import { useMaxScrollableHeight } from "./composable/useMaxScrollableHeight";
import { useLoadRecords } from "./composable/useLoadRecords";
import { ViewUrlParam } from "../js/types/pastoralReportTypes";

const store = useStore();
const url = window.Vue.ref(store.selectedTab?._links.view || "");
const urlParams = new URLSearchParams();
const { isNewLoad, isNewLoading, isMoreLoading, payload, loadError } = useLoadRecords(url);
const { maxScrollableHeight } = useMaxScrollableHeight();

/**
 * To display more results when there's lots to display and the user has scrolled to the bottom
 */
const scrollableEl = window.Vue.useTemplateRef<HTMLDivElement>("scrollableRef");
const { arrivedState } = useScroll(scrollableEl, {
    offset: { bottom: 30 },
});

const { bottom } = toRefs(arrivedState);
window.Vue.watch(bottom, (isAtBottom) => {
    if (isAtBottom && payload.value && payload.value.metadata.cursor.next !== null) {
        urlParams.set(ViewUrlParam.CURSOR as string, payload.value.metadata.cursor.next);
        url.value = `${store.selectedTab?._links.view}?${urlParams.toString()}`;
    }
});
</script>

<template>
    <div ref="scrollableRef" class="scrollable" :style="{ maxHeight: maxScrollableHeight }" data-test="pr-list">
        <div v-if="isNewLoad && isNewLoading" class="c-island l-pad-m" data-test="pr-list-loading">
            <div class="loader"></div>
        </div>
        <div v-else-if="isNewLoad && loadError" class="c-island l-pad-m">
            <data-load-error data-title="record" :further-info="loadError" />
        </div>
        <template v-else-if="payload">
            <div v-if="payload.data.results.length > 0" class="l-flex-col l-gap-s">
                <div v-for="record in payload.data.results" :key="record.id" class="c-island">
                    <ul class="information-list threaded">
                        <pr-record :record="record" />
                    </ul>
                </div>
            </div>
            <pr-empty v-else />
            <div v-if="isMoreLoading" class="c-island l-pad-s">
                <div class="loader"></div>
            </div>
            <data-load-error v-else-if="!isNewLoad && loadError" data-title="record" :further-info="loadError" />
        </template>
    </div>
</template>

<style scoped></style>
