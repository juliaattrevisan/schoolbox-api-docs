<script setup lang="ts">
/**
 * Paperly Dashboard Component
 * Allows users to access forms relevant to their role and
 * view upcoming tasks that require action
 */

import { payload } from "./tempPayload";
import type { PaperlyPayload } from "../js/types/PaperlyPayload";
import PdcUpcomingItem from "./pdc-upcoming-item.vue";

const tempPayload = window.Vue.ref<PaperlyPayload>(payload);
const viewMore = window.Vue.ref(false);

/**
 * Lists the upcoming tasks
 * @returns {UpcomingItem[]} - the upcoming tasks
 */
const upcoming = window.Vue.computed(() => {
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
</script>

<template>
    <div class="c-island l-flex-col">
        <pdc-upcoming-item v-for="item in upcoming" :key="item.url" :item="item" />
    </div>
    <div class="component-action">
        <section>
            <button @click.prevent="onViewMoreSelected">View more</button>
        </section>
    </div>
</template>

<style scoped></style>
