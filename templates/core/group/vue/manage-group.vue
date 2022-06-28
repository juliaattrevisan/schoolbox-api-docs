<script setup>

/**
 * All the functionality to be able to manage a group
 */

import { ref, computed, nextTick, onMounted  } from 'vue';
import ManageGroupActions from './manage-group-actions.vue';
import ManageGroupAddMembers from './manage-group-add-members.vue';
import { updateFooterUI } from '@schoolboxVue/components/vueTables2/useFooterUI';


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
    $(document).foundation(); //to enable the Foundation features such as the drop down in -actions and tabs in -add-members
});


/*****************************************************************
 * 
 * Load the group members
 * 
 ****************************************************************/

const isLoading = ref(true); //to display loading note
const hasLoadError = ref(false); //to display loading error
const groupMembers = ref([]); //all the group members

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
        isLoading.value = false;
        hasLoadError.value = true; //hide search bar in the dom
    } finally {
        isLoading.value = false;
    }
}


/*****************************************************************
 * 
 * Display the group members in a table
 * 
 ****************************************************************/

const tableColumns = ['name']
const tableOptions = { //options for vue-tables-2: https://matanya.gitbook.io/vue-tables-2/options-api
    // columnsClasses: {
    //     ['dateModified.formatted']: 'date-modified-td ', //to hide the column for mobile
    //     edit: 'edit-td ', //to specify a width
    // },
    debounce: 100, //shorter debounce time as data is stored locally
    headings: {
        name: 'Name',
        // ['dateModified.formatted']: 'Date modified',
    },
    perPage: 100,
    perPageValues: [100, 200, 300],
    resizableColumns: false,
    selectable: {
        mode: 'multiple' //to enable multi-select for those with permission
    },
    sortable: ['name'], //the edit column doesn't need to be sortable
    sortIcon: {
        is: 'glyphicon-sort',
        base: 'glyphicon',
        up: 'glyphicon-chevron-up',
        down: 'glyphicon-chevron-down'
    },
    tabbable: false,
    texts: {
        count: '{from}-{to} of {count} members|{count} members|1 member',
        filterPlaceholder: 'Search group members',
        noResults: 'No matching group members',
    },
}
const tableFooterEl = ref(null); 

/**
 * To update the table to show the UI in the footer
 */
async function onLoaded () {
    updateFooterUI(tableFooterEl);
}

</script>

<template>
    <div class="row actions-small-1">
        <div class="list-item small-12 columns">
            <h1>Manage Group</h1>
        </div>
        <manage-group-actions />
    </div><!-- row actions-small-1 -->
    <div class="row">
        <div class="small-12 island">
            <manage-group-add-members />
        </div><!-- small-12 island -->
    </div><!-- row  -->
    <div class="row">
        <div class="small-12 island">
            <h2 class="subheader">View Members</h2>
            <v-client-table
                ref="table"
                :data="groupMembers"
                :columns="tableColumns"
                :options="tableOptions"
                @loaded="onLoaded"
            >
                <template #afterTable>
                    <div class="VueTables__table-footer" ref="tableFooterEl"></div>
                </template>
            </v-client-table>
        </div><!-- small-12 island -->
    </div><!-- row  -->
</template>

<style lang="scss">

@import '@schoolboxVue/components/vueTables2/vueTables2';
    
</style>