<script setup>

/**
 * Display the group members in a table display
 */

import { ref, onMounted, watch } from 'vue';
import { EventBus } from 'v-tables-3-select-rows';
import { useStore } from './manageGroupStore';
import { updateFooterUI } from '@schoolboxVue/components/vueTables2/useFooterUI';
import ManageGroupTableFilters from './manage-group-table-filters.vue';
import Toast from '@schoolboxVue/components/toast.vue';
import FilterEvents from './manageGroupFilterEvents';
import FilterTypes from './manageGroupFilterTypes';



/*****************************************************************
 * 
 * Load the group members
 * 
 ****************************************************************/

const store = useStore();

onMounted(() => {
    store.loadGroupMembers();
});



/*****************************************************************
 * 
 * Display the group members in a table
 * 
 ****************************************************************/

const tableEl = ref(null);
const toastEl = ref(null);
const tableColumns = ['name', 'meta', 'access'];
const tableOptions = { //options for vue-tables-2: https://matanya.gitbook.io/vue-tables-2/options-api
    columnsClasses: {
        access: 'access-td ', //to specify a width
    },
    customFilters: [ //refer to https://matanya.gitbook.io/vue-tables-2/custom-filters for further info
        {
            name: FilterEvents.SHOW_WAITING_LIST,
            callback: (row, query) => row.access !== query,
        },
        {
            name: FilterEvents.SHOW_READ_ACCESS,
            callback: (row, query) => row.access !== query,
        },
        {
            name: FilterEvents.SHOW_WRITE_ACCESS,
            callback: (row, query) => row.access !== query,
        },
        {
            name: FilterEvents.SHOW_ADMIN_ACCESS,
            callback: (row, query) => row.access !== query,
        },
    ],
    debounce: 100, //shorter debounce time as data is stored locally
    headings: {
        name: 'Name',
        access: 'Access',
    },
    hiddenColumns: ['meta'], //the meta data is added to the name column, but we need a meta column so we can search for it
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
        filterPlaceholder: 'Search group members, they can be searched by name or role',
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
 * To approve a members membership request
 * 
 * @param { Object } related row 
 */
function onApproveMembershipSelected (row) {
    row.access = FilterTypes.READ;
    store.updateMemberAccessType(row, FilterTypes.READ);
}

/**
 * To update a group members access
 * 
 * @param { Object } related row 
 */
function onMemberAccessChange (row) {
    store.updateMemberAccessType(row, row.access);
}

/**
 * so we can track the progress of the member removal 
 * and update the UI accordingly
 */
store.$onAction(({ name, after, onError }) => {
    if (name === 'updateMemberAccessType') {
        // isProcessing.value = true;
        toastEl.value.activate('Updating...');
        after((updatedMember) => {
            // isProcessing.value = false;
            toastEl.value.timeout('Member successfully updated', true, 5000);
            //we need to do this as Vue is watching tableData, not each individual file within
            const memberInTableData = store.groupMembers.find((member) => member.id === updatedMember.id);
            memberInTableData.access = updatedMember.access;
        });
        onError((error) => {
            console.warn(error);
            // isProcessing.value = false;
            toastEl.value.timeout('Unable to update members', false, 5000);
        });
    }
});

/**
 * Reload the page to try loading again
 */
function onRefreshSelected () {
    location.reload();
}



/*****************************************************************
 * 
 * Select group members
 * 
 ****************************************************************/

/**
 * to keep track of selected rows
 * 
 * @param { Array }
 */
function onRowSelected (currentSelection) {
    store.updateSelectedMembers(currentSelection);
}

/**
 * ensure the table selection is also cleared when 'Clear Selection' is selected
 * or when the selected members have been removed
 */
watch(() => store.selectedMembers, (newSelectedMembers, oldSelectedMembers) => {
    if (newSelectedMembers.length === 0 && oldSelectedMembers.length > 0) { //to clear the current selection
        tableEl.value.resetSelectedRows();
    } 
});



/*****************************************************************
 * 
 * Filter the group members
 * 
 ****************************************************************/

/**
 * to update the table display based on the selected filters change
 */
watch(() => store.selectedFilters, (newSelectedFilters, oldSelectedFilters) => {
    let selectedFilter = []; //to store the filter that has been toggled
    if (newSelectedFilters.length < oldSelectedFilters.length) { //a filter has been taken out
        selectedFilter = oldSelectedFilters.filter(filterType => !newSelectedFilters.includes(filterType)); //comparing the two arrays to find the only value in oldSelectedFilters
    } else { //otherwise one has been added back in
        selectedFilter = newSelectedFilters.filter(filterType => !oldSelectedFilters.includes(filterType)); //comparing the two arrays to find the only value in newSelectedFilters
    }
    dispatchFilterEvent(selectedFilter.join()); //to just get the single filter as a string
    updateSelectedMembers();
});

/**
 * Modify what's displayed in the table, showing or hiding a filter type
 * depending on whether it's in the selected filters
 *
 * @param { String }
 */
function dispatchFilterEvent (selectedFilter) {
    const filterParam = store.selectedFilters.includes(selectedFilter) ? '' : selectedFilter ;
    const filterEventsType = {
        [FilterTypes.WAITING]: FilterEvents.SHOW_WAITING_LIST,
        [FilterTypes.READ]: FilterEvents.SHOW_READ_ACCESS,
        [FilterTypes.WRITE]: FilterEvents.SHOW_WRITE_ACCESS,
        [FilterTypes.ADMIN]: FilterEvents.SHOW_ADMIN_ACCESS,
    }
    EventBus.emit(`vue-tables.filter::${filterEventsType[selectedFilter]}`, filterParam);
}

/**
 * Ensure the current selectedMembers also considers the current filter selection, 
 * taking any out that no longer match the selected filters
 */
function updateSelectedMembers () {
    const newSelectedMembers = []; //to store the newly selected members with the filter change considered
    const membersToRemove = []; //to store those selected members that need to be removed due to the filter change
    store.selectedMembers.forEach((member) => {
        if (store.selectedFilters.some((accessType) => member.access == accessType)) {
            newSelectedMembers.push(member); //add the member to the new filtered list
        } else {
            membersToRemove.push(member.id); //otherwise pull it out
        }
    });
    if (membersToRemove.length > 0) {
        tableEl.value.unselectRows(membersToRemove); //pull out all the unselected members at once
    }
    store.updateSelectedMembers(newSelectedMembers);
}

</script>

<template>
   <div class="row">
        <div class="small-12 island">
            <h2 class="subheader">Current Members</h2>
            <v-client-table
                v-if="store.hasGroupMembers"
                ref="tableEl"
                :data="store.groupMembers"
                :columns="tableColumns"
                :options="tableOptions"
                @loaded="onLoaded"
                @select="onRowSelected"
            >
                <template #beforeTable>
                    <manage-group-table-filters />
                </template>
                <template #name="props">
                    <div class="card small-12">
                        <a href="#">
                            <img src="https://demo.schoolbox.com.au/images/fail.php?transform_type=portrait&transform=square64&code=500" :alt="`${props.row.name} portrait`">
                            {{ props.row.name }}
                        </a>
                        <p class="meta">{{ props.row.meta }}</p>
                    </div>
                </template>
                <template #access="props">
                    <button v-if="props.row.access === 'waiting'" class="button expand" title="Approve Membership" @click.prevent="onApproveMembershipSelected(props.row)">Approve Membership</button>
                    <select v-else id="access" v-model="props.row.access" @change="onMemberAccessChange(props.row)">
                        <option :value="FilterTypes.READ">Read Access</option>
                        <option :value="FilterTypes.WRITE">Write Access</option>
                        <option :value="FilterTypes.ADMIN">Group Admin</option>
                    </select>
                </template>
                <template #afterTable>
                    <div class="VueTables__table-footer" ref="tableFooterEl" data-table-footer></div>
                </template>
            </v-client-table>
            <div v-else-if="store.hasNoGroupMembers" class="empty-state">
                <i class="icon-group"></i>
                <p>This group currently has no members.<br>Once members have been added they will appear here.</p>
            </div>
            <div v-else-if="store.hasLoadError">
                <section class="content">
                    <div tableData-alert class="alert-box alert">
                        Sorry, we are currently unable to load the group members. Please <a href="#refresh-page" title="refresh the page" @click.prevent="onRefreshSelected">refresh the page</a> to try again contact and your support it the problem persists.
                    </div>
                </section>
            </div>
            <div v-else-if="store.isLoading">
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
    <toast ref="toastEl"></toast>
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

.VueTables__table .access-td {
    padding-left: 0; //bring it inline with the right side border
    width: 15rem; //keep the edit icon on the right side
}

</style>
