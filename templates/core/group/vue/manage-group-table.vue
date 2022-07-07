<script setup>

/**
 * Display the group members in a table display
 */

import { ref, onMounted, watch } from 'vue';
import { EventBus } from 'v-tables-3-select-rows';
import { useStore } from './manageGroupStore';
import { updateFooterUI } from '@schoolboxVue/components/vueTables2/useFooterUI';
import ManageGroupTableFilters from './manage-group-table-filters.vue';
import ManageGroupTableAccess from './manage-group-table-access.vue';
import FilterEvents from './manageGroupFilterEvents';
import AccessTypes from './manageGroupAccessTypes';


const props = defineProps({
    /**
     * To display the toast access status changes
     */
    toastEl: {
        required: true,
    },
});


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
const tableColumns = ['name', 'meta', 'accessType'];
const tableOptions = { //options for vue-tables-2: https://matanya.gitbook.io/vue-tables-2/options-api
    columnsClasses: {
        accessType: 'access-td ', //to specify a width
    },
    customFilters: [ //refer to https://matanya.gitbook.io/vue-tables-2/custom-filters for further info
        {
            name: FilterEvents.SHOW_WAITING_LIST,
            callback: (row, query) => row.accessType !== query,
        },
        {
            name: FilterEvents.SHOW_READ_ACCESS,
            callback: (row, query) => row.accessType !== query,
        },
        {
            name: FilterEvents.SHOW_WRITE_ACCESS,
            callback: (row, query) => row.accessType !== query,
        },
        {
            name: FilterEvents.SHOW_ADMIN_ACCESS,
            callback: (row, query) => row.accessType !== query,
        },
    ],
    debounce: 100, //shorter debounce time as data is stored locally
    headings: {
        name: 'Name',
        accessType: 'Access',
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
        [AccessTypes.WAITING]: FilterEvents.SHOW_WAITING_LIST,
        [AccessTypes.READ]: FilterEvents.SHOW_READ_ACCESS,
        [AccessTypes.WRITE]: FilterEvents.SHOW_WRITE_ACCESS,
        [AccessTypes.ADMIN]: FilterEvents.SHOW_ADMIN_ACCESS,
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
        if (store.selectedFilters.some((accessType) => member.accessType == accessType)) {
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
                <template #accessType="props">
                    <manage-group-table-access :row="props.row" :toast-el="props.toastEl" />
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
