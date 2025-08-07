<script lang="ts" setup>
import { useCohortReportStore } from "./store/cohortReportStore";

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
            label: entries[0].createdAt.displayAs,
            entries: entries.sort((a, b) => b.updatedAt.timestamp - a.updatedAt.timestamp),
        }));
});
</script>

<template>
    <div class="l-flex-col l-gap-m">
        <div v-if="crStore.loadError" class="alert-box alert">
            {{ crStore.loadError }}
        </div>
        <div v-else-if="crStore.isLoading" class="small-12 c-island">
            <section class="content">
                <div class="loader"></div>
            </section>
        </div>

        <div v-for="(group, index) in resultGroups" :key="index" class="small-12 c-island">
            <section class="content">
                <h2 class="l-mar--0-bottom">{{ group.label }}</h2>
                {{ group.entries }}
            </section>
        </div>

        <div class="small-12 c-island">
            <section class="content">
                {{ crStore.results }}
            </section>
        </div>
    </div>
</template>

<style scoped></style>
