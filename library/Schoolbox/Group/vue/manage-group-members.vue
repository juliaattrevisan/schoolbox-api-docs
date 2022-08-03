<script setup>

/**
 * Display the group members in a table display
 */

import { ref, watch } from 'vue';
import { EventBus } from 'v-tables-3-select-rows';
import { useStore } from './store/manageGroupStore';
import { useMembersStore } from './store/manageGroupMembersStore';
import { updateFooterUI } from '@schoolboxVue/components/vueTables2/useFooterUI';
import ManageGroupMembersFilters from './manage-group-members-filters.vue';
import ManageGroupMembersAccess from './manage-group-members-access.vue';
import FilterEvents from './manageGroupFilterEvents';
import AccessTypes from './manageGroupAccessTypes';



/*****************************************************************
 *
 * Display the group members in a table
 *
 ****************************************************************/

const store = useStore();
const membersStore = useMembersStore();
const tableEl = ref(null);
const tableColumns = ['name', 'firstName', 'lastName', 'role', 'campus', 'yearLevel', 'accessType'];
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
    customSorting: {
        name: (ascending) => { //'waiting' members to always show on top and then sort by lastName
            return (a, b) => {
                if (a.displayPriority < b.displayPriority) { //the displayPriority is set in manageGroupStore.js
                    return 1;
                } else if (a.displayPriority > b.displayPriority) {
                    return -1;
                }
                if (ascending) {
                    return a.lastName >= b.lastName ? 1 : -1;
                }
                return a.lastName <= b.lastName ? 1 : -1;
            }
        },
    },
    debounce: 100, //shorter debounce time as data is stored locally
    headings: {
        name: 'Name',
        accessType: 'Access',
    },
    hiddenColumns: ['firstName', 'lastName', 'role', 'campus', 'yearLevel'], //these are all added to name, but we need to add them to tableColumns so we can search them
    orderBy: { //initial sorting display
        ascending: true,
        column: 'name'
    },
    perPage: 50,
    perPageValues: [50, 100, 200],
    resizableColumns: false,
    rowClassCallback: (row) => { //to differentiate new / updated rows
        if (row.isNew) { //so it's clear the rows have just been added
            return 'is-new';
        } else if (row.justUpdated) { //and so it's clear they've just been updated
            return 'just-updated';
        }
        return '';
    },
    selectable: {
        mode: 'multiple' //to enable multi-select for those with permission
    },
    sortable: ['name'], //the access column doesn't need to be sortable
    tabbable: false,
    texts: {
        count: '{from}-{to} of {count} members|{count} members|1 member',
        filterPlaceholder: 'Search group members, they can be searched by name, year level, campus, role or access',
        noResults: 'No matching group members',
    },
}

/**
 * To update the table to show the UI in the footer
 */
async function onLoaded () {
    updateFooterUI();
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
    membersStore.updateSelectedMembers(currentSelection);
}

/**
 * ensure the table selection is also cleared when 'Clear Selection' is selected
 * or when the selected members have been removed
 */
watch(() => membersStore.selectedMembers, (newSelectedMembers, oldSelectedMembers) => {
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
watch(() => membersStore.selectedFilters, (newSelectedFilters, oldSelectedFilters) => {
    let selectedFilter = []; //to membersStore the filter that has been toggled
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
    const filterParam = membersStore.selectedFilters.includes(selectedFilter) ? '' : selectedFilter ;
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
    const newSelectedMembers = []; //to membersStore the newly selected members with the filter change considered
    const membersToRemove = []; //to membersStore those selected members that need to be removed due to the filter change
    membersStore.selectedMembers.forEach((member) => {
        if (membersStore.selectedFilters.some((accessType) => member.accessType == accessType)) {
            newSelectedMembers.push(member); //add the member to the new filtered list
        } else {
            membersToRemove.push(member.id); //otherwise pull it out
        }
    });
    if (membersToRemove.length > 0) {
        tableEl.value.unselectRows(membersToRemove); //pull out all the unselected members at once
    }
    membersStore.updateSelectedMembers(newSelectedMembers);
}

</script>

<template>
   <div class="row">
        <div class="small-12 island">
            <h2 class="subheader">Current Members</h2>
            <div v-if="membersStore.hasNoGroupAdmins" class="alert-box warning">Please note, this group currently has no Group Admins.</div>
            <v-client-table
                v-if="membersStore.hasGroupMembers"
                ref="tableEl"
                :data="membersStore.groupMembers"
                :columns="tableColumns"
                :options="tableOptions"
                @loaded="onLoaded"
                @select="onRowSelected"
                @sorted="membersStore.refreshMembers(true)"
                @pagination="membersStore.refreshMembers(true)"
                @filter="membersStore.refreshMembers(true)"
            >
                <template #beforeTable>
                    <manage-group-members-filters />
                </template>
                <template #name="data">
                    <div class="card small-12">
                        <a v-if="data.row.userId" :href="`/search/user/${data.row.userId}`" title="View profile" class="name">
                            <img :src="`/portrait.php?id=${data.row.userId}&size=square64`" :alt="`${data.row.firstName} portrait`">
                            {{ data.row.firstName }} {{ data.row.lastName }}
                        </a>
                        <div v-else>
                            <img src="/images/fail.php?transform_type=portrait&transform=square64&code=500" :alt="`${data.row.firstName} portrait`">
                            {{ data.row.firstName }} {{ data.row.lastName }}
                        </div>
                        <p class="meta">{{ data.row.role }}, {{ data.row.campus }}, {{ data.row.yearLevel }}</p>
                    </div>
                </template>
                <template #accessType="data">
                    <manage-group-members-access :member="data.row" />
                </template>
                <template #afterTable>
                    <div class="VueTables__table-footer" ref="tableFooterEl" data-table-footer></div>
                </template>
            </v-client-table>
            <div v-else-if="membersStore.hasNoGroupMembers" class="empty-state">
                <i class="icon-group"></i>
                <p>This group currently has no members.<br>Once members have been added they will appear here.</p>
            </div>
        </div><!-- small-12 island -->
    </div><!-- row  -->

</template>

<style lang="scss">

@import '@schoolboxVue/components/vueTables2/vueTables2';
@import '../../../../www/static/css/core/base/colors';

.VueTables {
    margin-bottom: 150px; //to allow space for the bottom-drawer so it can't cut off the bottom of the table
}

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
    width: 25rem; //keep the edit icon on the right side
}

.change-animation {
    animation-duration: 2s;
    animation-iteration-count: 1;
}

.VueTables__table tr.is-new { //have to be pretty specific to overwrite foundation rules...
    @extend .change-animation;
    animation-name: isNewAnimation;
}

@keyframes isNewAnimation {
    0% { background-color: $success-color; }
    100% { background-color: white; }
}

.VueTables__table tr.just-updated { //have to be pretty specific to overwrite foundation rules...
    @extend .change-animation;
    animation-name: justUpdatedAnimation;
}

@keyframes justUpdatedAnimation {
    0% { background-color: var(--accent-background); }
    100% { background-color: white; }
}


</style>
