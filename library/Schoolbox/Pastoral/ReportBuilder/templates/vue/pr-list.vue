<script setup lang="ts">
/**
 * To display a list of pastoral records in a list format
 */

import type { PastoralRecord } from "@schoolboxLibrary/Pastoral/types/PastoralRecord";
import type { Config, Routes, SeverityColors } from "../js/types/pastoralReportTypes";
import PrRecord from "./pr-record.vue";

const props = defineProps<{
    results: PastoralRecord[];
    config: Config;
    routes: Routes;
    localePastoral: string;
    showSeverity: boolean;
    severityColors: SeverityColors;
}>();
</script>

<template>
    <div class="row">
        <div v-if="props.results.length > 0" class="small-12 island">
            <section v-for="record in props.results" :key="record.id">
                <ul class="information-list threaded">
                    <pr-record
                        :record="record"
                        :config="props.config"
                        :routes="props.routes"
                        :locale-pastoral="props.localePastoral"
                        :show-severity="props.showSeverity"
                        :severity-colors="props.severityColors"
                    />
                </ul>
            </section>
        </div>
        <div v-else class="empty-state-flex-centered f-no-margins">
            <i class="icon-pastoral"></i>
            <p>No {{ props.localePastoral }} records matched your report criteria.</p>
            <a :href="props.routes.getIndex"> Select Another Report </a>
        </div>
    </div>
</template>

<style scoped></style>
