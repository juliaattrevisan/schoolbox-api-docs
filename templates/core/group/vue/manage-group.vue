<script setup>

/**
 * All the functionality to be able to manage a group
 */

import { ref, computed, onMounted  } from 'vue';
import { EventBus } from 'v-tables-3-select-rows';
import ManageGroupHeading from './manage-group-heading.vue';
import ManageGroupAddMembers from './manage-group-add-members.vue';
import ManageGroupFilters from './manage-group-filters.vue';
import BottomDrawer from '@schoolboxVue/components/bottomDrawer/bottom-drawer.vue';
import { updateFooterUI } from '@schoolboxVue/components/vueTables2/useFooterUI';
import FilterEvents from './manageGroupFilterEvents';


const props = defineProps({
    /**
     * the endpoint to retrieve all the group data from
     */
    endpoint: {
        type: String,
        required: true,
    },
});

onMounted(() => {
    $(document).foundation(); //to enable the Foundation features such as the drop down in -header and tabs in -add-members
});


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
        const response = await fetch(props.endpoint);
        const responseJson = await response.json();
        groupMembers.value = responseJson;
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

const tableEl = ref(null);
const tableColumns = ['name', 'access']
const tableOptions = { //options for vue-tables-2: https://matanya.gitbook.io/vue-tables-2/options-api
    // columnsClasses: {
    //     ['dateModified.formatted']: 'date-modified-td ', //to hide the column for mobile
    //     edit: 'edit-td ', //to specify a width
    // },
    customFilters: [ //refer to https://matanya.gitbook.io/vue-tables-2/custom-filters for further info
        {
            name: FilterEvents.SHOW_WAITING_LIST,
            callback: (row, query) => {
                return row.access !== query;
            }
        },
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
    sortable: ['name'], //the access column doesn't need to be sortable
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
 * To determine which meta info to display, based 
 * on their access type
 * 
 * @param { Object }
 * 
 * @returns { String }
 */
function getMemberMeta (row) {
    const memberMeta = {
        'waiting': `<i class="icon-open"></i>Waiting List`,
        'read': `<i class="icon-view"></i>Read Access`,
        'write': `<i class="icon-modify"></i>Write Access`,
        'admin': `<i class="icon-favourite"></i>Group Admin`,
    };
    return memberMeta[row.access];
}

/**
 * To update a group members access
 * 
 * @param { Object } related row 
 */
function onMemberAccessChange (row) {
    console.warn('onMemberAccessChange - ' + row.name);
}

/**
 * Reload the page to try loading again
 */
function onRefreshSelected () {
    location.reload();
}



/*****************************************************************
 * 
 * Filter the group members
 * 
 ****************************************************************/

const selectedFilters = ref(['waiting', 'read', 'write', 'admin']);

/**
 * Add/remove from selectedFilters and fire an event for the table
 *
 * @param { String }
 */
function onFilterChange (accessType) {
    updateSelectedFilters(accessType);
    dispatchFilterEvent(accessType);
}

/**
 * Add/remove from selectedFilters
 *
 * @param { String }
 */
function updateSelectedFilters (accessType) {
    if (selectedFilters.value.includes(accessType)) {
        selectedFilters.value = selectedFilters.value.filter((filterType) => filterType !== accessType);
    } else {
        selectedFilters.value = [...selectedFilters.value, accessType];
    }
}

/**
 * Modify what's displayed in the table, showing or hiding a filter type
 * depending on whether it's in the selected filters
 *
 * @param { String }
 */
function dispatchFilterEvent (accessType) {
    const filterParam = selectedFilters.value.includes(accessType) ? '' : accessType ;
    const filterEventsType = {
        'waiting': FilterEvents.SHOW_WAITING_LIST,
        'read': FilterEvents.SHOW_READ_ACCESS,
        'write': FilterEvents.SHOW_WRITE_ACCESS,
        'admin': FilterEvents.SHOW_ADMIN_ACCESS,
    };
    EventBus.emit(`vue-tables.filter::${filterEventsType[accessType]}`, filterParam);
}

// onMounted(() => {
//     initFilterListeners();
// });

/**
 * So we can ensure that rows that are removed through filters are also removed from the selected rows
 */
// function initFilterListeners () {
//     document.addEventListener(FilterEvents.SHOW_READ_ACCESS, () => onFilterChanged());
//     document.addEventListener(FilterEvents.SHOW_WRITE_ACCESS, () => onFilterChanged());
//     document.addEventListener(FilterEvents.SHOW_ADMIN_ACCESS, () => onFilterChanged());
// }

/**
 * Pull any rows that are no longer visible out of the selected rows
 */
// function onFilterChanged () {
    // const filteredRowsIds = this.$refs.table.allFilteredData.map((filteredRow) => filteredRow.id); //is the currently visible list of rows, mapped to just the ids
    // const selectedRowsIds = this.selectedRows.map((selectedRow) => selectedRow.id);
    // const rowsToUnselect = selectedRowsIds.filter((selectedRowId) => !filteredRowsIds.includes(selectedRowId)); //filter out any that aren't in the filtered list 
    // this.$refs.table.unselectRows(rowsToUnselect);
    // this.selectedRows = this.selectedRows.filter((selectedRow) => !rowsToUnselect.includes(selectedRow.id));
// }



/*****************************************************************
 * 
 * Select group members
 * 
 ****************************************************************/

const selectedMembers = ref([]);
const isShiftKeySelected = ref(false);
const hasSelectedShiftKey = ref(false);

onMounted(() => {
    initShiftKeyDetection();
});

/**
 * to hide a UI note informing of shift-selection once pressed once
 */
function initShiftKeyDetection () {
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Shift') {
            isShiftKeySelected.value = true;
        }
    });
    document.addEventListener('keyup', (event) => {
        if (event.key === 'Shift') {
            isShiftKeySelected.value = false;
        }
    });
}

/**
 * to keep track of selected rows
 * 
 * @param { Object }
 */
function onRowSelected (event) {
    selectedMembers.value = event;
    if (selectedMembers.value.length > 1 && isShiftKeySelected.value) { //to hide a UI note, notifying of availability of shift-select
        hasSelectedShiftKey.value = true;
    }
}

/**
 * To track how many are selected 
 *
 * @returns { Number }
 */
const selectedMembersCount = computed(() => {
    return selectedMembers.value.length;
});

/**
 * For proper count display, eg: '1 member' / '4 members'
 *
 * @returns { String }
 */
const selectedMembersCountWithRowName = computed(() => {
    if (selectedMembersCount.value === 0) {
        return `No members`;	
    } else if (selectedMembersCount.value === 1) {
        return `${selectedMembersCount.value} member`;	
    } else {
        return `${selectedMembersCount.value} members`;	
    }
});

/**
 * To determine whether we can show the approve membership 
 * button in the bottom drawer
 *
 * @returns { Boolean }
 */
const areAllSelectedOnWaitingList = computed(() => {
    return selectedMembers.value.every((member) => member.access === 'waiting');
});

/**
 * 
 */
function onClearMemberSelection () {
    selectedMembers.value = [];
    tableEl.value.resetSelectedRows();
}

/**
 * 
 */
function onRemoveMembersSelected () {
    
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
                ref="tableEl"
                :data="groupMembers"
                :columns="tableColumns"
                :options="tableOptions"
                @loaded="onLoaded"
                @select="onRowSelected"
            >
                <template #beforeTable>
                    <manage-group-filters 
                        :selected-filters="selectedFilters"
                        @on-filter-change="onFilterChange"
                    />
                </template>
                <template #name="props">
                    <div class="card small-12">
                        <a href="#">
                            <img src="https://demo.schoolbox.com.au/images/fail.php?transform_type=portrait&transform=square64&code=500" :alt="`${props.row.name} portrait`">
                            {{ props.row.name }}
                        </a>
                        <p class="meta" v-html="getMemberMeta(props.row)"></p>
                    </div>
                </template>
                <template #access="props">
                    <button v-if="props.row.access === 'waiting'" class="button expand" title="Approve Membership">Approve Membership</button>
                    <select v-else id="access" v-model="props.row.access" @change="onMemberAccessChange(props.row)">
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
                        Sorry, we are currently unable to load the group members. Please <a href="#refresh-page" title="refresh the page" @click.prevent="onRefreshSelected">refresh the page</a> to try again contact and your support it the problem persists.
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
    <bottom-drawer :showDrawer="selectedMembersCount > 0">
        <h3 class="selected-count"><em>{{ selectedMembersCountWithRowName }} selected</em></h3>
        <span v-if="!hasSelectedShiftKey" class="shift-select-note">Use Shift+Select to select multiple members</span>
        <button @click.prevent="onClearMemberSelection" class="button-clear">Clear member selection</button>
        <ul class="flex-list buttons">
            <li v-if="areAllSelectedOnWaitingList">
                <button @click.prevent="onRemoveMembersSelected" class="button submit icon-add">Approve Membership</button>
            </li>
            <li>
                <button @click.prevent="onRemoveMembersSelected" class="button submit icon-delete">Remove {{ selectedMembersCountWithRowName }}</button>
            </li>
        </ul>
    </bottom-drawer>
</template>

<style lang="scss">

@import '@schoolboxVue/components/vueTables2/vueTables2';

.VueTables__search {
    padding-bottom: 0.5rem; //to put the manage-group-filters.vue up closer to the search input
}

.VueTables__search-field label {
    display: none; //there's a 'Search files' in the input and 'Filter to' label below in manage-group-filters.vue, so not needed
}

.VueTables__search-field input {
    margin-bottom: 0; //to put the manage-group-filters.vue up closer to the search input
}

.selected-count {
    margin-right: 1rem;
}

.button-clear, .button-delete, .selected-count {
    display: inline-block;
}

.shift-select-note {
    color: #9a9a9a;
    font-size: 13px;
    font-style: italic;
    margin-right: 1rem;
}

.button-clear {
    background: none;
    font-size: 0.8rem;
    padding: 0px;
    vertical-align: baseline;
    &:hover {
        background: none;
    }
}

</style>