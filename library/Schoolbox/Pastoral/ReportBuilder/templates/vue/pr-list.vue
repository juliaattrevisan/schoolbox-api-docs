<script setup lang="ts">
/**
 * To display a list of pastoral records in a list format
 */

import { toRefs, type Ref } from "vue";
import { useScroll } from "@vueuse/core";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import PrRecord from "./pr-record.vue";
import PrEmpty from "./pr-empty.vue";
import { useLoadRecords } from "./composable/useLoadRecords";
import { usePrintRecords } from "./composable/usePrintRecords";
import { ViewUrlParam, type Tab } from "../js/types/pastoralReportTypes";

const props = defineProps<{
    scrollableEl: HTMLDivElement | null;
}>();

const selectedTab = window.Vue.inject<Ref<Tab>>("selectedTab");

/**
 * Load the initial records
 */
const url = window.Vue.ref("");
const urlParams = new URLSearchParams();
urlParams.set("limit", "10");
url.value = `${selectedTab?.value._links.view}?${urlParams.toString()}`;
const { isNewLoad, isNewLoading, isMoreLoading, payload, loadError } = useLoadRecords(url);

/**
 * To display more results when there's lots to display and the user has scrolled to the bottom
 */
window.Vue.onMounted(async (): Promise<void> => {
    await window.Vue.nextTick();
    const { arrivedState } = useScroll(props.scrollableEl, {
        offset: { bottom: 30 },
    });
    const { bottom } = toRefs(arrivedState);
    window.Vue.watch(bottom, (isAtBottom) => {
        if (isAtBottom && payload.value && payload.value.metadata.cursor.next !== null) {
            urlParams.set(ViewUrlParam.CURSOR as string, payload.value.metadata.cursor.next);
            url.value = `${selectedTab?.value._links.view}?${urlParams.toString()}`;
        }
    });
});

/**
 * Print all records in the list
 */
const containerEl = window.Vue.useTemplateRef<HTMLDivElement>("containerRef");
const { printAllRecords, isLoading: isLoadingPrint } = usePrintRecords(
    url,
    urlParams,
    payload,
    containerEl,
    selectedTab?.value._links.view,
);
defineExpose({ printAllRecords });
</script>

<template>
    <div ref="containerRef" data-test="pr-list">
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
    <basic-modal v-model:is-open="isLoadingPrint" size="s">
        <div class="l-flex-col l-flex--center l-gap-s">
            <div class="loader"></div>
            <h3><em>Generating print report, please wait... </em></h3>
        </div>
    </basic-modal>
</template>

<style scoped></style>
