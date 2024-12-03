<script setup lang="ts">
/**
 * To be able to filter the report and control any other settings
 */

import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { useFiltersStore } from "./store/ccFiltersStore";
import { useExportStore } from "./store/ccExportStore";

const filtersStore = useFiltersStore();
const exportStore = useExportStore();

const isModalOpen = window.Vue.ref(false); // to open/close the exporting to csv modal
</script>

<template>
    <div class="c-island l-flex-row l-gap-l">
        <div class="l-flex-col l-flex__item--1 l-gap-l">
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
        <div>
            <a
                class="l-flex-row l-gap-s f-no-bkg f-no-margins"
                aria-label="Export to CSV"
                title="Export to CSV"
                @click="isModalOpen = true"
            >
                <sbx-icon name="export" />
                Export to CSV
            </a>
        </div>
    </div>
    <basic-modal v-model:is-open="isModalOpen" :has-background-click="true">
        <div class="l-flex-col l-gap-m f-no-margins">
            <h2>Export to CSV</h2>
            <p>
                The usages exported will be the same as the currently selected options in the report. A zip file
                containing a separate CSV for each course will be generated. You will be sent a notification when it is
                ready.
            </p>
            <ul class="flex-list buttons">
                <li><button type="button" class="button" @click="isModalOpen = false">Cancel</button></li>
                <li>
                    <a
                        type="button"
                        class="button submit"
                        :href="exportStore.getEndpoint()"
                        @click="isModalOpen = false"
                        >Export</a
                    >
                </li>
            </ul>
        </div>
    </basic-modal>
</template>

<style scoped></style>
