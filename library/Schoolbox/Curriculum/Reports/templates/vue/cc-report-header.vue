<script setup lang="ts">
/**
 * To be able to filter the report and control any other settings
 */

import { useStore } from "./store/curriculumCoverageStore";

const store = useStore();
</script>

<template>
    <div class="c-island">
        <div class="checklist">
            <label>Report on</label>
            <ul class="option-list">
                <li v-for="reportOption in store.allReportOptions" :key="reportOption.value">
                    <input
                        :id="reportOption.value"
                        v-model="store.selectedReportOptions"
                        :value="reportOption.value"
                        type="checkbox"
                    />
                    <label :for="reportOption.value">{{ reportOption.label }}</label>
                </li>
            </ul>
        </div>
    </div>
</template>

<style scoped></style>
