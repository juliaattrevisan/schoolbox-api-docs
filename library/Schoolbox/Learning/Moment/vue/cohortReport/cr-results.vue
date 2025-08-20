<script lang="ts" setup>
import { useCohortReportStore } from "./store/cohortReportStore";
import CrResultsGroup from "./cr-results-group.vue";
import CrBulkUpdateDrawer from "./cr-bulk-update-drawer.vue";

const crStore = useCohortReportStore();

/**
 * The results split into pages per day (based on entry.createdAt.displayAs)
 * Items inside groups are sorted by entry.updatedAt.timestamp
 */
const resultGroups = window.Vue.computed(() => {
    const groups: Record<string, typeof crStore.results> = {};
    crStore.results.forEach((entry) => {
        const weekStart = entry.createdAt.displayAs;
        if (!groups[weekStart]) {
            groups[weekStart] = [];
        }
        groups[weekStart].push(entry);
    });

    return Object.values(groups)
        .sort((a, b) => b[0].createdAt.timestamp - a[0].createdAt.timestamp)
        .map((entries) => ({
            id: entries[0].createdAt.timestamp,
            label: entries[0].createdAt.displayAs,
            entries: entries.sort((a, b) => b.updatedAt.timestamp - a.updatedAt.timestamp),
        }));
});
</script>

<template>
    <div class="l-flex-col l-gap-m">
        <cr-results-group
            v-for="(group, i) in resultGroups"
            :id="group.id"
            :key="group.label"
            :label="group.label"
            :entries="group.entries"
            :start-expanded="i === 0"
        />

        <div v-if="crStore.loadError" class="alert-box alert">
            {{ crStore.loadError }}
        </div>
        <div v-else-if="crStore.isLoading" class="small-12 c-island">
            <section class="content">
                <div class="loader"></div>
            </section>
        </div>
        <button v-else-if="crStore.nextCursor !== null" @click="crStore.loadNextPage">Load more results</button>
        <div v-else class="alert-box">All results have been loaded</div>

        <cr-bulk-update-drawer />
    </div>
</template>

<style scoped></style>
