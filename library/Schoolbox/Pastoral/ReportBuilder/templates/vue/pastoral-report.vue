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
    tabs: Tab[];
    results: PastoralRecord[];
    config: Config;
    routes: Routes;
    localePastoral: string;
    showSeverity: boolean;
    severityColors: SeverityColors;
}>();

const selectedTab = window.Vue.ref(props.tabs[0]?.type ?? "");
const recordsAndPoints = window.Vue.ref(["Records", "Points"]);
const recordsOrPoints = window.Vue.ref("Records");

const isTemporaryTesting = window.Vue.ref(true); // Temporary flag for testing purposes
</script>

<template>
    <!-- <pr-table v-if="isTemporaryTesting" :records-and-points="recordsAndPoints" :records-or-points="recordsOrPoints" /> -->
    <dl v-if="props.tabs.length > 1" class="tabs">
        <dd
            v-for="tab in props.tabs"
            :key="tab.type"
            :class="{ active: selectedTab === tab.type }"
            @click="selectedTab = tab.type"
        >
            <a>{{ tab.title }}</a>
        </dd>
    </dl>
    <pr-list
        v-if="selectedTab === TabType.LIST"
        :results="props.results"
        :config="props.config"
        :routes="props.routes"
        :locale-pastoral="props.localePastoral"
        :show-severity="props.showSeverity"
        :severity-colors="props.severityColors"
    />
    <pr-table v-else :records-and-points="recordsAndPoints" :records-or-points="recordsOrPoints" />
</template>

<style scoped></style>
