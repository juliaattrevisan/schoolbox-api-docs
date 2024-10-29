<script setup lang="ts">
/**
 * To be able to filter the report and control any other settings
 */

import { useFiltersStore } from "./store/ccFiltersStore";

const filtersStore = useFiltersStore();
</script>

<template>
    <div class="c-island">
        <div class="checklist">
            <label>Report on</label>
            <ul class="option-list">
                <li v-for="reportOption in filtersStore.allReportOptions" :key="reportOption.value">
                    <input
                        :id="reportOption.value"
                        v-model="filtersStore.selectedReportOptions"
                        :value="reportOption.value"
                        type="checkbox"
                    />
                    <label :for="reportOption.value">{{ reportOption.label }}</label>
                </li>
            </ul>
        </div>
        <div>
            <label>Only show gaps</label>
            <div class="switch">
                <input id="onlyShowGapsSwitch" v-model="filtersStore.onlyShowGaps" type="checkbox" />
                <label for="onlyShowGapsSwitch">
                    <span class="icon-approve"></span>
                    <span class="icon-cancel"></span>
                </label>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
