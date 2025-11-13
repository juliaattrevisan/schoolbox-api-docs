<script setup lang="ts">
/**
 * Filters to select which dialog agent statuses to show
 */

import { fromEntriesOf } from "@js/util/arrayUtils";
import { DialogAgentResponseStatus, STATUS_TICKS, STATUS_LABELS } from "@schoolboxAssessment/Types/dialogAgent";

// TODO: this should be somewhere else (so that it's actually usable)
const filters = window.Vue.ref<Record<DialogAgentResponseStatus, boolean>>(
    fromEntriesOf(Object.values(DialogAgentResponseStatus).map((status) => [status, true])),
);
</script>

<template>
    <ul class="filters">
        <li v-for="status in Object.values(DialogAgentResponseStatus)" :key="status" class="l-mar-xs--left">
            <input :id="`das-filter-${status}`" v-model="filters[status]" type="checkbox" />
            <label :for="`das-filter-${status}`">
                <span class="progress-tick tiny" :class="STATUS_TICKS[status]"></span> {{ STATUS_LABELS[status] }}
            </label>
        </li>
    </ul>
</template>

<style scoped></style>
