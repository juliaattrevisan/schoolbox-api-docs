<script setup lang="ts">
/**
 * To display a list of pastoral records in a list format
 */

import type { PastoralRecord } from "@schoolboxLibrary/Pastoral/types/PastoralRecord";
import type { Config, Routes, SeverityColors } from "../js/types/pastoralReportTypes";
import PrRecord from "./pr-record.vue";
import PrEmpty from "./pr-empty.vue";

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
        <pr-empty v-else :routes="props.routes" :locale-pastoral="props.localePastoral" />
    </div>
</template>

<style scoped></style>
