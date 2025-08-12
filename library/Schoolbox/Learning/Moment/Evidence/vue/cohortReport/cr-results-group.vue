<script lang="ts" setup>
import { slideDown, slideUp } from "@js/util/animateUtils";
import { useCohortReportStore } from "./store/cohortReportStore";
import type { CohortReportEntry } from "./store/cohortReportPayload";

interface Props {
    label: string;
    entries: CohortReportEntry[];
    startExpanded: boolean;
}

const props = defineProps<Props>();
const crStore = useCohortReportStore();

const isExpanded = window.Vue.ref(props.startExpanded);
const showExpandedBorder = window.Vue.ref(isExpanded.value);
const entryListEl = window.Vue.useTemplateRef<HTMLDivElement>("entryListRef");

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

/**
 * Toggle the expanded state
 */
async function toggleExpanded(): Promise<void> {
    if (!entryListEl.value) {
        throw new Error("entryListEl is not defined");
    }

    isExpanded.value = !isExpanded.value;

    // showExpandedBorder is changed at a different point so the border-end class can be applied at the right time
    if (isExpanded.value) {
        showExpandedBorder.value = true;
        await slideDown(entryListEl.value);
    } else {
        await slideUp(entryListEl.value);
        showExpandedBorder.value = false;
    }
}

window.Vue.onMounted(() => {
    if (!entryListEl.value) {
        throw new Error("entryListEl is not defined");
    }

    isExpanded.value = props.startExpanded;
    showExpandedBorder.value = isExpanded.value;

    // Ensure the element matches the initial expanded state correctly (as slideDown/Up may not have run yet)
    entryListEl.value.style.display = isExpanded.value ? "" : "none";
});
</script>

<template>
    <div class="l-flex-col">
        <div class="c-card" :class="{ 'c-card--border-end': showExpandedBorder }">
            <div class="l-flex-row l-pad-s l-gap-s">
                <div class="l-flex-col l-flex--justify-center f-no-margins l-pad-s--left">
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
                <nav class="c-nav-actions l-flex-row">
                    <a :class="`icon-${isExpanded ? 'down' : 'up'}-arrow`" @click.prevent="toggleExpanded" />
                </nav>
            </div>
        </div>

        <div ref="entryListRef" class="c-card">
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
