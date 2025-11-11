<script setup lang="ts">
/**
 * To display a list of pastoral records in a list format
 */

import { toRefs, type Ref } from "vue";
import { useScroll, watchOnce } from "@vueuse/core";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import PrRecord from "./pr-record.vue";
import PrEmpty from "./pr-empty.vue";
import { useMaxScrollableHeight } from "./composable/useMaxScrollableHeight";
import { useLoadRecords } from "./composable/useLoadRecords";
import { ViewUrlParam, type Tab } from "../js/types/pastoralReportTypes";

const props = defineProps<{
    minWidth: number;
}>();

const selectedTab = window.Vue.inject<Ref<Tab>>("selectedTab");

/**
 * Keep the container below the height of the viewport
 */
const scrollableEl = window.Vue.useTemplateRef<HTMLDivElement>("scrollableRef");
// const { maxScrollableHeight } = useMaxScrollableHeight(scrollableEl, props.minWidth);

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
const { arrivedState } = useScroll(scrollableEl, {
    offset: { bottom: 30 },
});

const { bottom } = toRefs(arrivedState);
window.Vue.watch(bottom, (isAtBottom) => {
    if (isAtBottom && payload.value && payload.value.metadata.cursor.next !== null) {
        urlParams.set(ViewUrlParam.CURSOR as string, payload.value.metadata.cursor.next);
        url.value = `${selectedTab?.value._links.view}?${urlParams.toString()}`;
    }
});

/**
 * Wait for all images to load before showing print dialog
 */
async function waitForAllImages(): Promise<void> {
    await window.Vue.nextTick();

    // Find all images in the scrollable container
    const images = scrollableEl.value?.querySelectorAll("img") || [];

    // Create promises for each image
    const imagePromises = Array.from(images).map((img) => {
        return new Promise<void>((resolve) => {
            if (img.complete) {
                // Image already loaded
                resolve();
            } else {
                // Wait for image to load or error
                const onLoad = (): void => {
                    img.removeEventListener("load", onLoad);
                    img.removeEventListener("error", onLoad);
                    resolve();
                };
                img.addEventListener("load", onLoad);
                img.addEventListener("error", onLoad);
            }
        });
    });

    // Wait for all images to finish loading
    await Promise.all(imagePromises);
}

/**
 *
 */
function loadAllRecords(): void {
    urlParams.set("limit", "50");
    watchOnce(payload, async () => {
        // triggers only once - wait for all images to load before printing
        await waitForAllImages();
        window.print();
    });
    url.value = `${selectedTab?.value._links.view}?${urlParams.toString()}`;
}

defineExpose({ loadAllRecords });
</script>

<template>
    <div ref="scrollableRef" data-test="pr-list">
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
