<script setup lang="ts">
/**
 * To be able to filter the report and control any other settings
 */

import { useFiltersStore } from "./store/ccFiltersStore";

const filtersStore = useFiltersStore();
</script>

<template>
    <div class="c-island l-flex-col l-gap-l">
        <div class="checklist">
            <label>Report on</label>
            <p class="meta">Include usages linked to the selected options in the report.</p>
            <ul class="option-list l-flex l-gap-m f-no-margins">
                <li v-for="reportOption in filtersStore.allReportOptions" :key="reportOption.value">
                    <input
                        :id="reportOption.value"
                        v-model="filtersStore.selectedReportOptions"
                        :value="reportOption.value"
                        type="checkbox"
                    />
                    <label :for="reportOption.value" class="f-no-margins">{{ reportOption.label }}</label>
                </li>
            </ul>
        </div>
        <div>
            <label>Only show unused curriculum</label>
            <p class="meta">Only show curriculum that has a total usage count of 0.</p>
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
