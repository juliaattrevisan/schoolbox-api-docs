<script setup lang="ts">
/**
 * Pastoral Report Tabs Component
 */

import type { PastoralRecord } from "@schoolboxLibrary/Pastoral/types/PastoralRecord";
import { TabType } from "../js/types/pastoralReportTypes";
import type { Tab, Config, Routes, SeverityColors } from "../js/types/pastoralReportTypes";
import PrList from "./pr-list.vue";
import PrTable from "./table/pr-table.vue";

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
const recordsAndPoints = window.Vue.ref(["records", "points"]); // to toggle the tables between records and points
const recordsOrPoints = window.Vue.ref<"records" | "points">("records"); // the current records/points selection
const tabParamName = "tab"; // URL parameter name for the selected tab

/**
 * Handle tab selection and update URL without adding to browser history
 * @param tab - the tab that has been selected
 */
function onTabSelected(tab: Tab): void {
    selectedTab.value = tab;
    const url = new URL(window.location.href);
    url.searchParams.set(tabParamName, tab.key);
    window.history.replaceState(null, "", url.toString());
}

/**
 * Load the first tab when the component is mounted,
 * also checking the URL for a tab type.
 */
window.Vue.onMounted((): void => {
    // Check if there's a tab in the URL
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get(tabParamName);
    // and if so update the current tab if available
    if (tabParam) {
        const foundTab = props.tabs.find((tab) => tab.key === tabParam);
        if (foundTab) {
            selectedTab.value = foundTab;
        }
    }
});
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
                    v-if="selectedTab._links.export"
                    :href="selectedTab._links.export"
                    class="icon-download"
                    aria-label="Download Report as CSV"
                ></a>
                <!-- <a href="#" class="icon-print" title="Print Report Results" @click.prevent="onPrintSelected"></a> -->
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
                    @click="onTabSelected(tab)"
                >
                    <a>{{ tab.title }}</a>
                </dd>
            </dl>
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
                :title="props.title"
                :records-and-points="recordsAndPoints"
                :selected-tab="selectedTab"
                :config="props.config"
                :routes="props.routes"
                :locale-pastoral="props.localePastoral"
                :show-severity="props.showSeverity"
                :severity-colors="props.severityColors"
            />
        </div>
    </div>
</template>

<style>
:root {
    --sbx-pr-table-total-cell-colour: #eee;
}
</style>
