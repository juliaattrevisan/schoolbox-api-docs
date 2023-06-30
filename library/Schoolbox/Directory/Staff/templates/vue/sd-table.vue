<script setup lang="ts">
/**
 * Display the staff directory in a table display
 */

import { updateFooterUI } from "@schoolboxVue/components/vueTables2/useFooterUI";
import SdTableImage from "./sd-table-image.vue";

interface Props {
    data: unknown[]; // the staff data to display
}

const props = defineProps<Props>();

const tableEl = window.Vue.ref(null);
const columns = [
    "portrait_url",
    "StaffPreferredName",
    "StaffSurname",
    "StaffDepartment",
    "JobPositionDescription",
    "StaffCampusDescription",
    "StaffOccupPhone",
    "StaffExtension",
    "StaffOccupEmail",
];
const options = {
    // options for vue-tables-2: https://matanya.gitbook.io/vue-tables-2/options-api
    alwaysShowPerPageSelect: true,
    columnsClasses: {
        // to be able to add extra styles to columns
        portrait_url: "image-td",
        StaffDepartment: "hide-for-medium-down",
        JobPositionDescription: "hide-for-small",
        StaffCampusDescription: "hide-for-medium-down",
    },
    debounce: 100, // shorter debounce time as data is stored locally
    headings: {
        portrait_url: "Image",
        StaffPreferredName: "First name",
        StaffSurname: "Surname",
        StaffDepartment: "Department",
        JobPositionDescription: "Role",
        StaffCampusDescription: "Campus",
        StaffOccupPhone: "Phone",
        StaffExtension: "Ext",
        StaffOccupEmail: "Email",
    },
    // hiddenColumns: ["firstName", "givenName", "preferredName", "lastName", "role", "campus", "yearLevel", "house"], // these are all added to name, but we need to add them to columns so we can search them
    orderBy: {
        // initial sorting display
        ascending: true,
        column: "StaffSurname",
    },
    perPage: 50,
    perPageValues: [50, 100, 200],
    resizableColumns: false,
    // sortable: ["name"], // the access column doesn't need to be sortable
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
    <v-client-table ref="tableEl" :data="props.data" :columns="columns" :options="options" @loaded="onLoaded">
        <template #portrait_url="tableData">
            <sd-table-image :data="tableData.row" />
        </template>
        <template #afterTable>
            <div ref="tableFooterEl" class="VueTables__table-footer" data-table-footer></div>
        </template>
    </v-client-table>
</template>

<style lang="scss">
@import "@schoolboxVue/components/vueTables2/vueTables2";

//
@media only screen and (max-width: 800px) {
    .VueTables__table .StaffDepartment-td,
    .VueTables__table .StaffCampusDescription-td {
        display: none;
    }
}
</style>
