<script setup lang="ts">
/**
 * To display a list of pastoral records in a list format
 */

import { toRefs, type Ref } from "vue";
import { useScroll, watchOnce } from "@vueuse/core";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import { waitForAllImages } from "@js/util/domUtils";
import PrRecord from "./pr-record.vue";
import PrEmpty from "./pr-empty.vue";
import { useLoadRecords } from "./composable/useLoadRecords";
import { ViewUrlParam, type Tab } from "../js/types/pastoralReportTypes";

const props = defineProps<{
    scrollableEl: HTMLDivElement | null;
}>();

const selectedTab = window.Vue.inject<Ref<Tab>>("selectedTab");
const containerEl = window.Vue.useTemplateRef<HTMLDivElement>("containerRef");

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
 * Load all records for printing
 */
function loadAllRecords(): void {
    urlParams.set("limit", "1000");
    watchOnce(payload, async () => {
        // triggers only once - wait for all images to load before printing
        await window.Vue.nextTick();
        await waitForAllImages(containerEl.value);
        window.print();
    });
    url.value = `${selectedTab?.value._links.view}?${urlParams.toString()}`;
}

defineExpose({ loadAllRecords });
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
</template>

<style scoped></style>
