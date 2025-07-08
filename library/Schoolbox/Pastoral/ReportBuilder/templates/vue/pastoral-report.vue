<script setup lang="ts">
/**
 * Pastoral Report Tabs Component
 */

import type { PastoralReportTab, SeverityColors, PastoralRecord } from "../js/types/pastoralReportTypes";
import PrList from "./pr-list.vue";
import PrTable from "./pr-table.vue";

const props = defineProps<{
    tabs: PastoralReportTab[];
    results: PastoralRecord[];
    showSeverity: boolean;
    severityColors: SeverityColors;
    pastoralPointEnabled: boolean | number;
    pastoralActionEnable: boolean | number;
    localePastoral: string;
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
        v-if="selectedTab === 'list'"
        :results="props.results"
        :show-severity="props.showSeverity"
        :severity-colors="props.severityColors"
        :pastoral-point-enabled="props.pastoralPointEnabled"
        :pastoral-action-enable="props.pastoralActionEnable"
        :locale-pastoral="props.localePastoral"
    />
    <pr-table v-else :records-and-points="recordsAndPoints" :records-or-points="recordsOrPoints" />
</template>

<style scoped></style>
