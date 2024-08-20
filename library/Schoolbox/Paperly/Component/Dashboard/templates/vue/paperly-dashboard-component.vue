<script setup lang="ts">
/**
 * Paperly Dashboard Component
 * Allows users to access forms relevant to their role and
 * view upcoming tasks that require action
 */

import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import type { PaperlyPayload } from "../js/types/PaperlyPayload";
import type { UpcomingItem } from "../js/types/UpcomingItem";
import PdcUpcomingItem from "./pdc-upcoming-item.vue";
import { UpcomingItem } from "../js/types/UpcomingItem";

interface Props {
    getPaperlyViewEndpoint: string; // The endpoint for fetching the list of Paperly items and its iframe urls
}

const props = defineProps<Props>();

const paperlyPayload = window.Vue.ref<PaperlyPayload>({ features: [], upcoming: [] }); // the payload from paperly
const viewMore = window.Vue.ref(false); // to show all upcoming tasks, only show the first three by default
const isProcessing = window.Vue.ref(false); // to display loading while the payload is being loaded
const hasLoadError = window.Vue.ref(false); // to show an error message if the payload fails to load

/**
 * Load the payload from paperly - currently a dummy payload
 */
async function loadPayload() {
    try {
        isProcessing.value = true;
        const response = await fetch(props.getPaperlyViewEndpoint);
        if (response.ok) {
            const responseJson = (await response.json()) as PaperlyPayload;
            // check the response has the expected properties
            if (responseJson.features && responseJson.upcoming) {
                paperlyPayload.value = responseJson;
            } else {
                hasLoadError.value = true;
            }
        } else {
            hasLoadError.value = true;
        }
    } catch (error) {
        hasLoadError.value = true;
    } finally {
        isProcessing.value = false;
    }
}

/**
 * On mounted, load the payload
 */
window.Vue.onMounted(() => {
    void loadPayload();
});

/**
 * Lists the upcoming tasks
 * @returns {UpcomingItem[]} - the upcoming tasks
 */
const upcoming = window.Vue.computed<UpcomingItem[]>(() => {
    // if the payload is not loaded, return an empty array
    if (paperlyPayload.value === undefined) {
        return [];
    }
    // if view more has not been selected, only show the first three
    if (!viewMore.value) {
        return paperlyPayload.value.upcoming.slice(0, 3);
    }
    // otherwise show all upcoming tasks
    return paperlyPayload.value.upcoming;
});
</script>

<template>
    <div v-if="isProcessing" class="row">
        <div class="small-12 island">
            <loading-table />
        </div>
    </div>
    <data-load-error v-else-if="hasLoadError" data-title="Paperly" />
    <template v-else>
        <ul class="flex-list flex-wrap buttons">
            <li v-for="feature in paperlyPayload.features" :key="feature.url">
                <a :class="feature.icon ?? ''" :href="feature.url ?? ''">{{ feature.title ?? "" }}</a>
            </li>
        </ul>
        <div class="c-island l-flex-col">
            <h3 v-if="upcoming.length > 0" class="l-pad-m--horiz l-pad-s--vert f-no-margins">
                Upcoming Tasks (Action required)
            </h3>
            <div v-if="upcoming.length === 0" class="empty-state">
                <i class="icon-paperly-forms"></i>
                <p>There are currently no upcoming tasks that require action.</p>
            </div>
            <pdc-upcoming-item v-for="item in upcoming" v-else :key="item.url" :item="item" />
        </div>
        <div v-if="paperlyPayload && paperlyPayload.upcoming.length > 3 && !viewMore" class="component-action">
            <section>
                <button @click.prevent="viewMore = true">View more</button>
            </section>
        </div>
    </template>
</template>

<style scoped>
h3 {
    background-color: #f5f5f5; /* to help distinguish the title */
    border-bottom: 1px solid #efefef;
}
</style>
