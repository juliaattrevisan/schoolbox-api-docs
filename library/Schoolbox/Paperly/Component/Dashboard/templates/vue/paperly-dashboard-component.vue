<script setup lang="ts">
/**
 * Paperly Dashboard Component
 * Allows users to access forms relevant to their role and
 * view upcoming tasks that require action
 */

import { timeout } from "@js/util/jsUtils";
import LoadingTable from "@schoolboxVue/components/loading/loading-table";
import { payload } from "./tempPayload";
import type { PaperlyPayload } from "../js/types/PaperlyPayload";
import PdcUpcomingItem from "./pdc-upcoming-item.vue";

const tempPayload = window.Vue.ref<PaperlyPayload | undefined>();
const viewMore = window.Vue.ref(false); // to show all upcoming tasks, only show the first three by default
const isProcessing = window.Vue.ref(false); // to disable further backend actions while we're already processing
const hasLoadError = window.Vue.ref(false); // to show an error message if the payload fails to load

/**
 * Load the payload from paperly - currently a dummy payload
 */
async function loadPayload() {
    try {
        isProcessing.value = true;
        const response = await fetch(`/api/curriculum/`, {
            headers: {
                "Content-Type": "application/json",
            },
        });
        if (response.ok) {
            // const payload = (await response.json()) as PaperlyPayload;
            tempPayload.value = payload;
        } else {
            hasLoadError.value = true;
        }
    } catch (error) {
        hasLoadError.value = true;
    } finally {
        await timeout(2000);
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
const upcoming = window.Vue.computed(() => {
    if (tempPayload.value === undefined) {
        return [];
    }
    // if view more has not been selected, only show the first three
    if (!viewMore.value) {
        return tempPayload.value.upcoming.slice(0, 3);
    }
    return tempPayload.value.upcoming;
});

/**
 * To show all upcoming tasks
 */
function onViewMoreSelected() {
    viewMore.value = true;
}

/**
 * Reload the page to try loading again
 */
function onRefreshSelected() {
    window.location.reload();
}
</script>

<template>
    <div v-if="isProcessing" class="row">
        <div class="small-12 island">
            <loading-table />
        </div>
    </div>
    <div v-else-if="hasLoadError" class="row">
        <div class="small-12 island">
            <section class="content">
                <div tableData-alert class="alert-box alert">
                    Sorry, we are currently unable to load the Paperly data. Please
                    <a href="#refresh-page" title="refresh the page" @click.prevent="onRefreshSelected"
                        >refresh the page</a
                    >
                    to try again and contact your support if the problem persists.
                </div>
            </section>
        </div>
    </div>
    <template v-else>
        <ul class="flex-list flex-wrap buttons">
            <li v-if="tempPayload?.features.find((item) => 'Forms' in item)">
                <a class="icon-paperly-forms" :href="tempPayload.features.find((item) => 'Forms' in item)?.Forms ?? ''">
                    Forms</a
                >
            </li>
            <li v-if="tempPayload?.features.find((item) => 'Excursions' in item)">
                <a
                    class="icon-paperly-excursions"
                    :href="tempPayload.features.find((item) => 'Excursions' in item)?.Excursions ?? ''"
                    >Excursions</a
                >
            </li>
            <li v-if="tempPayload?.features.find((item) => 'PTI' in item)">
                <a class="icon-paperly-pti" :href="tempPayload.features.find((item) => 'PTI' in item)?.PTI ?? ''"
                    >Parent/Teacher Interview</a
                >
            </li>
        </ul>
        <div class="c-island l-flex-col">
            <h3 class="l-pad-m--horiz l-pad-s--vert f-no-margins">Upcoming Tasks (Action required)</h3>
            <p v-if="upcoming.length === 0" class="meta l-pad-m--horiz l-pad-s--vert f-no-margins">
                There are currently no upcoming tasks that require action
            </p>
            <pdc-upcoming-item v-for="item in upcoming" v-else :key="item.url" :item="item" />
        </div>
        <div v-if="tempPayload && tempPayload.upcoming.length > 3" class="component-action">
            <section>
                <button @click.prevent="onViewMoreSelected">View more</button>
            </section>
        </div>
    </template>
</template>

<style scoped></style>
