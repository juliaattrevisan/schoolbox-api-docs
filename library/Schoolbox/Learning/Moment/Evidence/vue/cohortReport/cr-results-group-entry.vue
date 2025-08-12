<script lang="ts" setup>
import { useCohortReportStore } from "./store/cohortReportStore";
import type { CohortReportEntry } from "./store/cohortReportPayload";

interface Props {
    entry: CohortReportEntry;
    dataPrefix: string;
}

const props = defineProps<Props>();
const crStore = useCohortReportStore();

const inputId = window.Vue.computed(() => {
    return `${props.dataPrefix}-select-${props.entry.id}`;
});
</script>

<template>
    <input
        :id="inputId"
        type="checkbox"
        :name="inputId"
        :checked="crStore.isEntrySelected(entry.id)"
        @change="crStore.toggleEntrySelection(entry.id)"
    />
    <label :for="inputId" class="checkbox-label"></label>

    <p>{{ entry.body }} - {{ entry.createdAt.displayAs }}</p>
</template>

<style scoped></style>
