<script setup lang="ts">
/**
 * Pastoral Report Tabs Component
 */

import type { PastoralRecord } from "@schoolboxLibrary/Pastoral/types/PastoralRecord";
import { TabType } from "../js/types/pastoralReportTypes";
import type { Tab, Config, Routes, SeverityColors } from "../js/types/pastoralReportTypes";
import { useStore } from "./store/pastoralReportStore";
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

const store = useStore();
const recordsOrPoints = window.Vue.ref<"records" | "points">("records"); // the current records/points selection
const tabParamName = "tab"; // URL parameter name for the selected tab

/**
 * Handle tab selection and update URL without adding to browser history
 * @param tab - the tab that has been selected
 */
function onTabSelected(tab: Tab): void {
    store.selectedTab = tab;
    const url = new URL(window.location.href);
    url.searchParams.set(tabParamName, tab.key);
    window.history.replaceState(null, "", url.toString());
}

/**
 * Initialize the store with the provided parameters.
 */
window.Vue.onBeforeMount((): void => {
    store.init({
        title: props.title,
        export: props.export,
        reportId: props.reportId,
        tabs: props.tabs,
        config: props.config,
        routes: props.routes,
        localePastoral: props.localePastoral,
        showSeverity: props.showSeverity,
        severityColors: props.severityColors,
    });
});

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
            store.selectedTab = foundTab;
        }
    }
});
</script>

<template>
    <div class="l-flex-col l-gap-m">
        <div class="l-flex-row l-flex--justify-space l-flex--align-end f-no-margins">
            <h1>{{ props.title }}</h1>
            <nav v-if="props.results.length">
                <a
                    v-if="store.selectedTab?._links.export"
                    :href="store.selectedTab._links.export"
                    aria-label="Download Report as CSV"
                    title="Download Report as CSV"
                >
                    <sbx-icon name="download" />
                </a>
                <!-- <a href="#" class="icon-print" title="Print Report Results" @click.prevent="onPrintSelected"></a> -->
            </nav>
        </div>
        <div>
            <dl v-if="props.tabs.length > 1" class="tabs">
                <dd
                    v-for="tab in props.tabs"
                    :key="tab.type"
                    :class="{ active: store.selectedTab?.title === tab.title }"
                    @click="onTabSelected(tab)"
                >
                    <a>{{ tab.title }}</a>
                </dd>
            </dl>
            <pr-list v-if="store.selectedTab?.type === TabType.LIST" :results="props.results" />
            <pr-table v-else v-model:records-or-points="recordsOrPoints" />
        </div>
    </div>
</template>

<style>
:root {
    --sbx-pr-table-total-cell-colour: #eee;
    --sbx-pr-table-row-hover-colour: #f4f3f3;
}
</style>
