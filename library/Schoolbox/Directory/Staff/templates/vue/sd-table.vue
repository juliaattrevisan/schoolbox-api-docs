<script setup lang="ts">
/**
 * Display the staff directory in a table display
 */

import { updateFooterUI } from "@schoolboxVue/components/vueTables2/useFooterUI";
import SdTableImage from "./sd-table-image.vue";
import SdTablePhone from "./sd-table-phone.vue";
import SdTableEmail from "./sd-table-email.vue";
import type { ColumnsConfig, ColumnsData } from "./staffDirectory";

interface Props {
    data: ColumnsData[]; // the staff data to display
    columnsConfig: ColumnsConfig[]; // the columns to display in the table
}
const props = defineProps<Props>();

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

const options = {
    // options for vue-tables-2: https://matanya.gitbook.io/vue-tables-2/options-api
    alwaysShowPerPageSelect: true,
    columnsClasses,
    debounce: 100, // shorter debounce time as data is stored locally
    headings,
    orderBy: {
        // initial sorting display
        ascending: true,
        column: "StaffSurname",
    },
    perPage: 50,
    perPageValues: [50, 100, 200],
    resizableColumns: false,
    tabbable: false,
    texts: {
        count: "{from}-{to} of {count} members|{count} members|1 member",
        filterPlaceholder: "Search staff members...",
        noResults: "No matching staff members",
    },
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
    <v-client-table :data="props.data" :columns="columns" :options="options" @loaded="onLoaded">
        <template #portrait_url="tableData">
            <sd-table-image :data="tableData.row" />
        </template>
        <template #StaffOccupPhone="tableData">
            <sd-table-phone :data="tableData.row" />
        </template>
        <template #StaffOccupEmail="tableData">
            <sd-table-email :data="tableData.row" />
        </template>
        <template #afterTable>
            <div ref="tableFooterEl" class="VueTables__table-footer" data-table-footer></div>
        </template>
    </v-client-table>
</template>

<style lang="scss">
@import "@schoolboxVue/components/vueTables2/vueTables2";
</style>
