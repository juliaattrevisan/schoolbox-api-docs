<script lang="ts" setup>
import { slideDown, slideUp } from "@js/util/animateUtils";
import { useCohortReportStore } from "./store/cohortReportStore";
import type { CohortReportEntry } from "./store/cohortReportPayload";
import CrResultsGroupEntry from "./cr-results-group-entry.vue";

interface Props {
    id: string | number;
    label: string;
    entries: CohortReportEntry[];
    startExpanded: boolean;
}

const props = defineProps<Props>();
const crStore = useCohortReportStore();

const isExpanded = window.Vue.ref(props.startExpanded);
const showExpandedBorder = window.Vue.ref(isExpanded.value);
const entryListEl = window.Vue.useTemplateRef<HTMLDivElement>("entryListRef");

const dataPrefix = window.Vue.computed(() => `cr-results-group-${props.id}`);

/** Whether all entries in this group are selected */
const allEntriesSelected = window.Vue.computed(() => {
    return props.entries.every((entry) => crStore.isEntrySelected(entry.id));
});

/**
 * Toggle selection of all entries in this group
 */
function toggleAllEntries(): void {
    const newState = !allEntriesSelected.value;
    props.entries.forEach((entry) => {
        if (newState === crStore.isEntrySelected(entry.id)) {
            return; // state is the same, no need to toggle
        }

        crStore.toggleEntrySelection(entry.id);
    });
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
    <div class="u-flex-col">
        <div class="sbx-card l-c-cr-top" :class="{ 'sbx-card--border-end': showExpandedBorder }">
            <div class="u-flex-row u-pad-s u-gap-s">
                <div class="u-flex-col u-flex--justify-center f-no-margins u-pad-s--left">
                    <input
                        :id="`${dataPrefix}-selectAll`"
                        type="checkbox"
                        :name="`${dataPrefix}-selectAll`"
                        :checked="allEntriesSelected"
                        @change="toggleAllEntries"
                    />
                    <label :for="`${dataPrefix}-selectAll`" class="checkbox-label"> </label>
                </div>
                <div class="u-flex-col u-flex--justify-center u-flex__item--1 f-no-margins">
                    <h2>{{ props.label }}</h2>
                </div>
                <nav class="sbx-nav-actions u-flex-row">
                    <a :class="`icon-${isExpanded ? 'up' : 'down'}-arrow`" @click.prevent="toggleExpanded" />
                </nav>
            </div>
        </div>

        <div ref="entryListRef" class="sbx-card l-c-cr-bottom">
            <div class="cr-results-grid u-grid u-gap-m u-pad-m">
                <cr-results-group-entry
                    v-for="entry in props.entries"
                    :key="entry.id"
                    :entry="entry"
                    :data-prefix="dataPrefix"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.cr-results-grid {
    grid-template-columns: repeat(auto-fill, minmax(30rem, 1fr));
}

@media screen and (max-width: 60rem) {
    .cr-results-grid {
        grid-template-columns: 1fr;
    }
}
</style>
