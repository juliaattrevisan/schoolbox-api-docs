<script setup lang="ts">
/**
 * Display the staff directory in a table display
 */

import { updateFooterUI } from "@schoolboxVue/components/vueTables2/useFooterUI";
import StaffDirectoryTableImage from "./staff-directory-table-image.vue";
// import MgMembersName from "./mg-members-name.vue";
// import MgMembersAccess from "./mg-members-access.vue";

interface Props {
    data: unknown[]; // the staff data to display
}

const props = defineProps<Props>();

const tableEl = window.Vue.ref(null);
const tableColumns = [
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
const tableOptions = {
    // options for vue-tables-2: https://matanya.gitbook.io/vue-tables-2/options-api
    alwaysShowPerPageSelect: true,
    columnsClasses: {
        name: "name-td ",
        accessType: "access-td ", // to specify a width
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
    // hiddenColumns: ["firstName", "givenName", "preferredName", "lastName", "role", "campus", "yearLevel", "house"], // these are all added to name, but we need to add them to tableColumns so we can search them
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
    <v-client-table ref="tableEl" :data="props.data" :columns="tableColumns" :options="tableOptions" @loaded="onLoaded">
        <template #portrait_url="tableData">
            <staff-directory-table-image :data="tableData.row" />
        </template>
        <!-- <template #accessType="data">
            <mg-members-access :member="data.row" />
        </template>  -->
        <template #afterTable>
            <div ref="tableFooterEl" class="VueTables__table-footer" data-table-footer></div>
        </template>
    </v-client-table>
</template>

<style lang="scss">
@import "@schoolboxVue/components/vueTables2/vueTables2";

// .VueTables__search-field label {
//     display: none; //there's a 'Search files' in the input and 'Filter to' label below in manage-group-filters.vue, so not needed
// }

// .VueTables__search-field input {
//     margin-bottom: 0; //to put the manage-group-filters.vue up closer to the search input
// }

// .VueTables__table .name-td .meta {
//     white-space: normal; // to allow the meta to wrap, which prevents the chance of horizontal scrolling in the table
// }

// .VueTables__table .access-td {
//     padding-left: 0; //bring it inline with the right side border
//     width: 25rem; //keep the edit icon on the right side
//     min-width: 7.5rem; //ensure the column is always wide enough
// }

// // Make moderation buttons inline on mobile
// @media only screen and (max-width: 800px) {
//     .VueTables__table .access-td .flex-list {
//         display: block;
//     }
//     .VueTables__table .access-td {
//         max-width: 8rem;
//     }
// }
</style>
