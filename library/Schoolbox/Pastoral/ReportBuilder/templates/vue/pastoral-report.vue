<script setup lang="ts">
/**
 * Pastoral Report Tabs Component
 */

import type { PastoralRecord } from "@schoolboxLibrary/Pastoral/types/PastoralRecord";
import { TabType } from "../js/types/pastoralReportTypes";
import type { Tab, Config, Routes, SeverityColors } from "../js/types/pastoralReportTypes";
import PrList from "./pr-list.vue";
import PrTable from "./pr-table.vue";

const props = defineProps<{
    title: string;
    export: boolean;
    reportId: number;
    tabs: Tab[];
    results: PastoralRecord[];
    config: Config;
    routes: Routes;
    localePastoral: string;
    showSeverity: boolean;
    severityColors: SeverityColors;
}>();

const selectedTab = window.Vue.ref(props.tabs[0]); // the currently selected tab, defaulting to the first tab type
const recordsAndPoints = window.Vue.ref(["Records", "Points"]); // to toggle the tables between records and points
const recordsOrPoints = window.Vue.ref("Records"); // the current records/points selection

const isTemporaryTesting = window.Vue.ref(true); // Temporary flag for testing purposes

/**
 * Handle print button click
 */
function onPrintSelected(): void {
    window.print();
}
</script>

<template>
    <div class="row actions-small-1">
        <div class="list-item">
            <div class="small-12 columns">
                <h1>{{ props.title }}</h1>
            </div>
        </div>
        <nav>
            <template v-if="props.results.length">
                <a
                    v-if="props.export"
                    :href="props.routes.getReportResultsExport.replace('{reportId}', props.reportId.toString())"
                    class="icon-download"
                ></a>
                <a href="#" class="icon-print" title="Print Report Results" @click.prevent="onPrintSelected"></a>
            </template>
        </nav>
    </div>
    <div class="row">
        <div class="small-12 island">
            <dl v-if="props.tabs.length > 1" class="tabs">
                <dd
                    v-for="tab in props.tabs"
                    :key="tab.type"
                    :class="{ active: selectedTab.title === tab.title }"
                    @click="selectedTab = tab"
                >
                    <a>{{ tab.title }}</a>
                </dd>
            </dl>
            <!-- <pr-table
                v-if="isTemporaryTesting"
                v-model:records-or-points="recordsOrPoints"
                :records-and-points="recordsAndPoints"
                :selected-tab="selectedTab"
            /> -->
            <pr-list
                v-if="selectedTab.type === TabType.LIST"
                :results="props.results"
                :config="props.config"
                :routes="props.routes"
                :locale-pastoral="props.localePastoral"
                :show-severity="props.showSeverity"
                :severity-colors="props.severityColors"
            />
            <pr-table
                v-else
                v-model:records-or-points="recordsOrPoints"
                :records-and-points="recordsAndPoints"
                :selected-tab="selectedTab"
            />
        </div>
    </div>
</template>

<style scoped></style>
