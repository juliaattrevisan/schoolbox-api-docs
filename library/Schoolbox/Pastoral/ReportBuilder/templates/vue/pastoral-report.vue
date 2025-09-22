<script setup lang="ts">
/**
 * Pastoral Report Tabs Component
 */

import { TabType } from "../js/types/pastoralReportTypes";
import type { Tab, Config, Routes, SeverityColors } from "../js/types/pastoralReportTypes";
import PrList from "./pr-list.vue";
import PrTable from "./table/pr-table.vue";

const props = defineProps<{
    title: string;
    export: boolean;
    reportId: number;
    tabs: Tab[];
    config: Config;
    routes: Routes;
    localePastoral: string;
    showSeverity: boolean;
    severityColors: SeverityColors;
}>();

// Create reactive state
const selectedTab = window.Vue.ref<Tab | null>(null);
const recordsOrPoints = window.Vue.ref<"records" | "points">("records"); // the current records/points selection
const tabParamName = "tab"; // URL parameter name for the selected tab

// Provide the state and props to child components
window.Vue.provide("selectedTab", selectedTab);
window.Vue.provide("title", props.title);
window.Vue.provide("export", props.export);
window.Vue.provide("reportId", props.reportId);
window.Vue.provide("tabs", props.tabs);
window.Vue.provide("config", props.config);
window.Vue.provide("routes", props.routes);
window.Vue.provide("localePastoral", props.localePastoral);
window.Vue.provide("showSeverity", props.showSeverity);
window.Vue.provide("severityColors", props.severityColors);

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
    // Set the first tab as default if no tab is selected
    if (!selectedTab.value && props.tabs.length > 0) {
        const [firstTab] = props.tabs;
        selectedTab.value = firstTab;
    }
});
</script>

<template>
    <div class="l-flex-col l-gap-m">
        <div class="l-flex-row l-flex--justify-space l-flex--align-end f-no-margins">
            <h1>{{ props.title }}</h1>
            <nav>
                <a
                    v-if="`${selectedTab?._links.export}`"
                    :href="`${selectedTab?._links.export}?selected=${recordsOrPoints}`"
                    aria-label="Download Report as CSV"
                    title="Download Report as CSV"
                >
                    <sbx-icon name="download" />
                </a>
                <!--             <a href="#" class="icon-print" title="Print Report Results" @click.prevent="onPrintSelected"></a> -->
            </nav>
        </div>
        <div>
            <dl v-if="props.tabs.length > 1" class="tabs">
                <dd
                    v-for="tab in props.tabs"
                    :key="tab.type"
                    :class="{ active: selectedTab?.title === tab.title }"
                    @click="onTabSelected(tab)"
                >
                    <a>{{ tab.title }}</a>
                </dd>
            </dl>
            <template v-if="selectedTab">
                <pr-list v-if="selectedTab.type === TabType.LIST" />
                <pr-table v-else v-model:records-or-points="recordsOrPoints" />
            </template>
        </div>
    </div>
</template>

<style>
:root {
    --sbx-pr-table-total-cell-colour: #eee;
    --sbx-pr-table-row-hover-colour: #f4f3f3;
    --sbx-pr-table-first-header-width: 250px;
    --sbx-pr-table-total-header-width: 110px;
}
</style>
