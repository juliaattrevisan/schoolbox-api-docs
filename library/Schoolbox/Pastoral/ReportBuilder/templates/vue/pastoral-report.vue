<script setup lang="ts">
/**
 * Pastoral Report Tabs Component
 */
import type { PastoralReportTab } from "../js/types/pastoralReportTypes";

const props = defineProps<{
    tabs: PastoralReportTab[];
}>();

const selectedTab = window.Vue.ref(props.tabs[0]?.type ?? "");
</script>

<template>
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
</template>

<style scoped></style>
