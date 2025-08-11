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
    <div class="small-12 c-island">
        <section class="content">
            <fieldset>
                <input
                    :id="`${dataPrefix}-selectAll`"
                    type="checkbox"
                    :name="`${dataPrefix}-selectAll`"
                    :checked="allEntriesSelected"
                    @change="toggleAllEntries"
                />
                <label :for="`${dataPrefix}-selectAll`" class="checkbox-label">
                    <h2 class="l-mar--0-bottom">{{ props.label }}</h2>
                </label>
            </fieldset>

            <fieldset>
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
            </fieldset>
        </section>
    </div>
</template>

<style scoped></style>
