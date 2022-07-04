<script setup>

/**
 * Display the group members in a table display
 */

import { ref, onMounted, watch } from 'vue';
import { EventBus } from 'v-tables-3-select-rows';
import { useStore } from './manageGroupStore';
import { updateFooterUI } from '@schoolboxVue/components/vueTables2/useFooterUI';
import ManageGroupTableFilters from './manage-group-table-filters.vue';
import FilterEvents from './manageGroupFilterEvents';
import FilterTypes from './manageGroupFilterTypes';


const props = defineProps({
    /**
     * the endpoint to retrieve all the group data from
     */
    endpoint: {
        type: String,
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
    store.loadGroupMembers(props.endpoint);
});



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
        [FilterTypes.WAITING]: `<i class="icon-open"></i>Waiting List`,
        [FilterTypes.READ]: `<i class="icon-view"></i>Read Access`,
        [FilterTypes.WRITE]: `<i class="icon-modify"></i>Write Access`,
        [FilterTypes.ADMIN]: `<i class="icon-favourite"></i>Group Admin`,
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
 * remove the note once a selection with shift key down has been detected
 */
watch(() => store.selectedMembers, (newSelectedMembers) => {
    if (newSelectedMembers.length === 0) { //to clear the current selection
        tableEl.value.resetSelectedRows();
    } 
});



/*****************************************************************
 * 
 * Filter the group members
 * 
 ****************************************************************/

const selectedFilters = ref([FilterTypes.WAITING, FilterTypes.READ, FilterTypes.WRITE, FilterTypes.ADMIN]);

/**
 * Add/remove from selectedFilters and fire an event for the table
 *
 * @param { String }
 */
function onFilterChange (accessType) {
    updateSelectedFilters(accessType);
    dispatchFilterEvent(accessType);
    updateSelectedMembers();
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
        [FilterTypes.WAITING]: FilterEvents.SHOW_WAITING_LIST,
        [FilterTypes.READ]: FilterEvents.SHOW_READ_ACCESS,
        [FilterTypes.WRITE]: FilterEvents.SHOW_WRITE_ACCESS,
        [FilterTypes.ADMIN]: FilterEvents.SHOW_ADMIN_ACCESS,
    }
    EventBus.emit(`vue-tables.filter::${filterEventsType[accessType]}`, filterParam);
}

/**
 * Ensure the current selectedMembers also considers the current filter selection, 
 * taking any out that no longer match the selected filters
 */
function updateSelectedMembers () {
    const newSelectedMembers = [];
    store.selectedMembers.forEach((member) => {
        if (selectedFilters.value.some((accessType) => member.access == accessType)) {
            newSelectedMembers.push(member); //add the member to the new filtered list
        } else {
            tableEl.value.unselectRow(member.id); //otherwise pull it out
        }
    });
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
                    <manage-group-table-filters 
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

</style>
