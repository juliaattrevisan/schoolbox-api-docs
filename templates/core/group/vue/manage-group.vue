<script setup>

/**
 * All the functionality to be able to manage a group
 */

import { ref, computed, onMounted  } from 'vue';
import ManageGroupHeading from './manage-group-heading.vue';
import ManageGroupAddMembers from './manage-group-add-members.vue';
import ManageGroupFilters from './manage-group-filters.vue';
import { updateFooterUI } from '@schoolboxVue/components/vueTables2/useFooterUI';
import FilterEvents from './manageGroupFilterEvents';

import { data } from '../../../../stories/pages/manageGroup/dataManageGroup';


const props = defineProps({
    /**
     * the endpoint to retrieve all the group data from
     */
    endpoint: {
        type: String,
        required: true,
    },
});

// onMounted(() => {
//     $(document).foundation(); //to enable the Foundation features such as the drop down in -header and tabs in -add-members
// });


/*****************************************************************
 * 
 * Load the group members
 * 
 ****************************************************************/

const isLoading = ref(true); //to display loading note
const hasLoadError = ref(false); //to display loading error
const groupMembers = ref([]); //all the group members

/**
 * When the table is ready and able to be displayed with group members
 * 
 * @returns { Boolean } 
 */
const hasGroupMembers = computed(() => {
    return !isLoading.value && !hasLoadError.value && groupMembers.value.length > 0;
});

/**
 * When we've loaded the group members but there's none in the group yet
 * 
 * @returns { Boolean } 
 */
const hasNoGroupMembers = computed(() => {
    return !isLoading.value && !hasLoadError.value && groupMembers.value.length === 0;
});

onMounted(() => {
    loadGroupMembers();
});

/**
 * load all the group members for tabular display
 */
async function loadGroupMembers () {
    try {
        // const response = await fetch(props.endpoint);
        // const responseJson = await response.json();
        // groupMembers.value = responseJson;
        groupMembers.value = data;
        groupMembers.value.forEach((groupMember) => { //to highlight new members just added
            groupMember = { ...groupMember, isNew: false };
        });
    } catch (error) {
        console.warn(error);
        hasLoadError.value = true; //show the load error display state 
    } finally {
        isLoading.value = false;
    }
}


/*****************************************************************
 * 
 * Display the group members in a table
 * 
 ****************************************************************/

const tableColumns = ['name', 'access']
const tableOptions = { //options for vue-tables-2: https://matanya.gitbook.io/vue-tables-2/options-api
    // columnsClasses: {
    //     ['dateModified.formatted']: 'date-modified-td ', //to hide the column for mobile
    //     edit: 'edit-td ', //to specify a width
    // },
    customFilters: [ //refer to https://matanya.gitbook.io/vue-tables-2/custom-filters for further info
        {
            name: FilterEvents.SHOW_READ_ACCESS,
            callback: (row, query) => {
                return row.access !== query;
            }
        },
        {
            name: FilterEvents.SHOW_WRITE_ACCESS,
            callback: (row, query) => {
                return row.access !== query;
            }
        },
        {
            name: FilterEvents.SHOW_ADMIN_ACCESS,
            callback: (row, query) => {
                return row.access !== query;
            }
        },
    ],
    debounce: 100, //shorter debounce time as data is stored locally
    headings: {
        name: 'Name',
        access: 'Access',
        // ['dateModified.formatted']: 'Date modified',
    },
    perPage: 50,
    perPageValues: [50, 100, 200],
    resizableColumns: false,
    selectable: {
        mode: 'multiple' //to enable multi-select for those with permission
    },
    // sortable: ['name', 'access'], //the edit column doesn't need to be sortable
    tabbable: false,
    texts: {
        count: '{from}-{to} of {count} members|{count} members|1 member',
        filterPlaceholder: 'Search group members',
        noResults: 'No matching group members',
    },
}

/**
 * To update the table to show the UI in the footer
 */
async function onLoaded () {
    updateFooterUI();
}

/**
 * To update a group members access
 * 
 * @param { Object } related row 
 */
function onMemberAccessChange (row) {
    console.warn('onMemberAccessChange - ' + row.name);
}



/*****************************************************************
 * 
 * Filter the group members
 * 
 ****************************************************************/

onMounted(() => {
    initFilterListeners();
});

/**
 * So we can ensure that rows that are removed through filters are also removed from the selected rows
 */
function initFilterListeners () {
    document.addEventListener(FilterEvents.SHOW_READ_ACCESS, () => onFilterChanged());
    document.addEventListener(FilterEvents.SHOW_WRITE_ACCESS, () => onFilterChanged());
    document.addEventListener(FilterEvents.SHOW_ADMIN_ACCESS, () => onFilterChanged());
}

/**
 * Pull any rows that are no longer visible out of the selected rows
 */
function onFilterChanged () {
    // const filteredRowsIds = this.$refs.table.allFilteredData.map((filteredRow) => filteredRow.id); //is the currently visible list of rows, mapped to just the ids
    // const selectedRowsIds = this.selectedRows.map((selectedRow) => selectedRow.id);
    // const rowsToUnselect = selectedRowsIds.filter((selectedRowId) => !filteredRowsIds.includes(selectedRowId)); //filter out any that aren't in the filtered list 
    // this.$refs.table.unselectRows(rowsToUnselect);
    // this.selectedRows = this.selectedRows.filter((selectedRow) => !rowsToUnselect.includes(selectedRow.id));
}

</script>

<template>
    <manage-group-heading />
    <manage-group-add-members />
    <div class="row">
        <div class="small-12 island">
            <h2 class="subheader">Current Members</h2>
            <v-client-table
                v-if="hasGroupMembers"
                :data="groupMembers"
                :columns="tableColumns"
                :options="tableOptions"
                @loaded="onLoaded"
            >
                <template #beforeTable>
                    <manage-group-filters />
                </template>
                <template #access="props">
                    <!-- <nav class="edit">
                        <button :class="`button-toggle-visibility ${ props.row.hidden ? 'icon-view-hollow' : 'icon-view' }`" @click.prevent="onToggleVisibilitySelected(props.row)" :title="getVisibilityAction(props.row)">{{ getVisibilityAction(props.row) }}</button>
                        <a class="icon-modify" :href="props.row.editUrl" title="Modify File"></a>
                    </nav> -->
                    <select id="access" v-model="props.row.access" @change="onMemberAccessChange(props.row)">
                        <option value="read">Read Access</option>
                        <option value="write">Write Access</option>
                        <option value="admin">Group Admin</option>
                    </select>
                </template>
                <template #afterTable>
                    <div class="VueTables__table-footer" ref="tableFooterEl" data-table-footer></div>
                </template>
            </v-client-table>
            <div v-else-if="hasNoGroupMembers" class="empty-state">
                <i class="icon-group"></i>
                <p>This group currently has no members.<br>Once members have been added they will appear here.</p>
            </div>
            <div v-else-if="hasLoadError">
                <section class="content">
                    <div tableData-alert class="alert-box alert">
                        Sorry, we are currently unable to load the group members. Please contact your support it the problem persists.
                    </div>
                </section>
            </div>
            <div v-else-if="isLoading">
                <table class="skeleton">
                    <thead>
                        <tr>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td></td>
                        </tr>
                        <tr>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div><!-- small-12 island -->
    </div><!-- row  -->
</template>

<style lang="scss">

@import '@schoolboxVue/components/vueTables2/vueTables2';
    
</style>