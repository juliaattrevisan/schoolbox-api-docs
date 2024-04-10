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
            // document.dispatchEvent(
            //     new CustomEvent(ToastEvent.TIMEOUT, {
            //         detail: { text: "Curriculum updated" } as ToastTimeoutEventDetail,
            //     }),
            // );
        } else {
            hasLoadError.value = true;
            // const responseText = (await response.json()) as { title: string; type: string; status: number };
            // document.dispatchEvent(
            //     new CustomEvent(ToastEvent.TIMEOUT, {
            //         detail: { text: responseText.title, isSuccess: false } as ToastTimeoutEventDetail,
            //     }),
            // );
        }
    } catch (error) {
        // document.dispatchEvent(
        //     new CustomEvent(ToastEvent.TIMEOUT, {
        //         detail: { text: "There was an unexpected error", isSuccess: false } as ToastTimeoutEventDetail,
        //     }),
        // );
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
    // if view more has not been selected, only show the first three
    if (tempPayload.value === undefined) {
        return [];
    }
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
</script>

<template>
    <div v-if="isProcessing" class="row">
        <div class="small-12 island">
            <loading-table />
        </div>
    </div>
    <template v-else>
        <div class="c-island l-flex-col">
            <pdc-upcoming-item v-for="item in upcoming" :key="item.url" :item="item" />
        </div>
        <div class="component-action">
            <section>
                <button @click.prevent="onViewMoreSelected">View more</button>
            </section>
        </div>
    </template>
</template>

<style scoped></style>
