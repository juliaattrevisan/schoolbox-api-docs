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
        // groupMembers.value = responseJson;
        // groupMembers.value.forEach((groupMember) => { //to highlight new members just added
        //     groupMember = { ...groupMember, isNew: false };
        // });
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

const tableColumns = ['name', 'access']
const tableOptions = { //options for vue-tables-2: https://matanya.gitbook.io/vue-tables-2/options-api
    // columnsClasses: {
    //     ['dateModified.formatted']: 'date-modified-td ', //to hide the column for mobile
    //     edit: 'edit-td ', //to specify a width
    // },
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
    sortable: ['name', 'access'], //the edit column doesn't need to be sortable
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
            <h2 class="subheader">Current Members</h2>
            <v-client-table
                v-if="hasGroupMembers"
                :data="groupMembers"
                :columns="tableColumns"
                :options="tableOptions"
                @loaded="onLoaded"
            >
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