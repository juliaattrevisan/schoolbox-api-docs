<script setup lang="ts">
/**
 * To display the staff directory in a sortable, searchable table
 */

import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import { updateFooterUI } from "@schoolboxVue/components/vueTables2/useFooterUI";
import getDefaultOptions from "@schoolboxVue/components/vueTables2/getDefaultOptions";
import DataTableImage from "./templates/data-table-image.vue";
import DataTableLink from "./templates/data-table-link.vue";
import type { ColumnsConfig } from "./dataTable";

interface Props {
    endpoint: string; // the endpoint to retrieve all the staff data from
    columnsConfig: ColumnsConfig[]; // the columns to display in the table
    tableOptions: object; // the configuration for the vue-tables-2 component
    templates: object; //
}
const props = defineProps<Props>();

const data = window.Vue.ref([] as unknown[]); // the staff data to display
const isLoading = window.Vue.ref(true); // whether the staff data has been loaded
const hasLoadError = window.Vue.ref(false); // whether there was an error loading the staff data

/**
 * load the staff data for display
 */
async function loadData() {
    try {
        const response = await fetch(props.endpoint);
        data.value = (await response.json()) as unknown[];
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

// the columns to display in the table, retrieved by getting the data key from the column config
const columns: string[] = props.columnsConfig.map((column: ColumnsConfig) => column.data);

// to return an object where each heading is the key and the value is the class
const columnsClasses = props.columnsConfig.reduce((accumulator: object, column: ColumnsConfig) => {
    accumulator[column.data] = column.class;
    return accumulator;
}, {});

// to return an object where each heading is the key and the value is the data
const headings = props.columnsConfig.reduce((accumulator: object, column: ColumnsConfig) => {
    accumulator[column.data] = column.heading;
    return accumulator;
}, {});

// its assumed all columns are sortable unless isSortable is set to false
const sortable: string[] = [];
props.columnsConfig.forEach((column) => {
    if (!Object.prototype.hasOwnProperty.call(column, "isSortable")) {
        sortable.push(column.data);
    }
});

// the configuration for the vue-tables-2 component
const options = {
    ...getDefaultOptions(),
    columnsClasses,
    headings,
    ...props.tableOptions,
    sortable,
};

let isFirstLoad = true;

/**
 * To update the table to show the UI in the footer
 */
async function onLoaded() {
    if (isFirstLoad) {
        // loaded gets called on every state change, and we only want to do the below once
        isFirstLoad = false;
        await updateFooterUI();
    }
}
</script>

<template>
    <div class="row">
        <div class="small-12 columns">
            <loading-table v-if="isLoading" />
            <data-load-error v-else-if="hasLoadError" data-title="Staff Directory" />
            <v-client-table :data="data" :columns="columns" :options="options" @loaded="onLoaded">
                <template v-for="slot in props.templates" :key="slot.name" #[slot.name]="tableData">
                    <template v-if="slot.component === 'image'">
                        <data-table-image :row-data="tableData.row" :component-data="slot.componentData" />
                    </template>
                    <template v-else-if="slot.component === 'link'">
                        <data-table-link :row-data="tableData.row" :component-data="slot.componentData" />
                    </template>
                </template>
                <template #afterTable>
                    <div ref="tableFooterEl" class="VueTables__table-footer" data-table-footer></div>
                </template>
            </v-client-table>
        </div>
    </div>
</template>

<style lang="scss">
@import "@schoolboxVue/components/vueTables2/vueTables2";
</style>
