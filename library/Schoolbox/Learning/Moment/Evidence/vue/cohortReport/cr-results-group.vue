<script lang="ts" setup>
import { useCohortReportStore } from "./store/cohortReportStore";
import type { CohortReportEntry } from "./store/cohortReportPayload";

interface Props {
    label: string;
    entries: CohortReportEntry[];
}

const props = defineProps<Props>();
const crStore = useCohortReportStore();
</script>

<template>
    <div class="small-12 c-island">
        <section class="content">
            <h2 class="l-mar--0-bottom">{{ props.label }}</h2>
            <p v-for="entry in props.entries" :key="entry.id">{{ entry.body }} - {{ entry.createdAt.displayAs }}</p>
        </section>
    </div>
</template>

<style scoped></style>
