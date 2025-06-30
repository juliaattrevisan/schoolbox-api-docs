<script setup lang="ts">
import { ref } from "vue";
import type { PastoralReportTab } from "../js/types/pastoralReportTypes";

const props = defineProps<{
    tabs: PastoralReportTab[];
}>();

const { tabs } = props;

const selectedTab = ref(tabs[0]?.type ?? "");
</script>

<template>
    <div>
        <div v-if="tabs.length > 1" class="tabs">
            <button
                v-for="tab in tabs"
                :key="tab.type"
                :class="{ active: selectedTab === tab.type }"
                @click="selectedTab = tab.type"
            >
                {{ tab.title }}
            </button>
        </div>
    </div>
</template>

<style scoped></style>
