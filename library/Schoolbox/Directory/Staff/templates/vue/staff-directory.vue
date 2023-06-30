<script setup lang="ts">
/**
 * To display the staff directory in a sortable, searchable table
 */

import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import SdTable from "./sd-table.vue";
import type { ColumnsConfig } from "./staffDirectory";

interface Props {
    endpoint: string; // the endpoint to retrieve all the staff data from
    columnsConfig: ColumnsConfig[]; // the columns to display in the table
}
const props = defineProps<Props>();

const data = window.Vue.ref([] as any[]); // the staff data to display
const isLoading = window.Vue.ref(true); // whether the staff data has been loaded
const hasLoadError = window.Vue.ref(false); // whether there was an error loading the staff data

/**
 * load the staff data for display
 */
async function loadData() {
    try {
        const response = await fetch(props.endpoint);
        data.value = await response.json();
    } catch (error) {
        hasLoadError.value = true; // display load error note
    } finally {
        isLoading.value = false; // hide the loading table
    }
}

/**
 * Start loading the group data
 */
window.Vue.onMounted(() => {
    loadData();
});
</script>

<template>
    <div class="row">
        <div class="small-12 columns">
            <loading-table v-if="isLoading" />
            <data-load-error v-else-if="hasLoadError" data-title="Staff Directory" />
            <sd-table v-else :data="data" :columns-config="props.columnsConfig" />
        </div>
    </div>
</template>

<style scoped></style>
