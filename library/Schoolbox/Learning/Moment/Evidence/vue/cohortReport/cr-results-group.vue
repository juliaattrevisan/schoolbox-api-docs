<script lang="ts" setup>
import { useCohortReportStore } from "./store/cohortReportStore";
import type { CohortReportEntry } from "./store/cohortReportPayload";

interface Props {
    label: string;
    entries: CohortReportEntry[];
}

const props = defineProps<Props>();
const crStore = useCohortReportStore();

const dataPrefix = window.Vue.computed(() => {
    const label = props.label.replaceAll(" ", "-");
    return `cr-results-group-${label}`;
});

/**
 * @param entryId The entry ID to check
 * @returns whether the entry is selected
 */
function isEntrySelected(entryId: number): boolean {
    return !!crStore.selectedResults[entryId];
}

/**
 * Toggle selection of a specific entry
 * @param entryId The entry ID to toggle
 */
function toggleEntrySelection(entryId: number): void {
    if (crStore.selectedResults[entryId]) {
        delete crStore.selectedResults[entryId];
    } else {
        crStore.selectedResults[entryId] = true;
    }
}

/** Whether all entries in this group are selected */
const allEntriesSelected = window.Vue.computed(() => {
    return props.entries.every((entry) => isEntrySelected(entry.id));
});

/**
 * Toggle selection of all entries in this group
 */
function toggleAllEntries(): void {
    if (allEntriesSelected.value) {
        // Deselect all entries in this group
        props.entries.forEach((entry) => {
            delete crStore.selectedResults[entry.id];
        });
    } else {
        // Select all entries in this group
        props.entries.forEach((entry) => {
            crStore.selectedResults[entry.id] = true;
        });
    }
}
</script>

<template>
    <div class="l-flex-col">
        <div class="c-card c-card--border-end">
            <div class="l-flex-row l-pad-m l-gap-s">
                <div class="l-flex-col l-flex--justify-center f-no-margins">
                    <input
                        :id="`${dataPrefix}-selectAll`"
                        type="checkbox"
                        :name="`${dataPrefix}-selectAll`"
                        :checked="allEntriesSelected"
                        @change="toggleAllEntries"
                    />
                    <label :for="`${dataPrefix}-selectAll`" class="checkbox-label"> </label>
                </div>
                <div class="l-flex-col l-flex--justify-center l-flex__item--1 f-no-margins">
                    <h2>{{ props.label }}</h2>
                </div>
            </div>
        </div>
        <div class="c-card">
            <div class="l-flex-col l-gap-s l-pad-m">
                <template v-for="entry in props.entries" :key="entry.id">
                    <input
                        :id="`${dataPrefix}-select-${entry.id}`"
                        type="checkbox"
                        :name="`${dataPrefix}-select-${entry.id}`"
                        :checked="isEntrySelected(entry.id)"
                        @change="toggleEntrySelection(entry.id)"
                    />
                    <label :for="`${dataPrefix}-select-${entry.id}`" class="checkbox-label"></label>

                    <p>{{ entry.body }} - {{ entry.createdAt.displayAs }}</p>
                </template>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
