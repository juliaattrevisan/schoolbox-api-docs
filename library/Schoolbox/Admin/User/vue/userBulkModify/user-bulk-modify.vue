<script>

/**
 * Bulk modify selected users
 */

import UserBulkModifyFilters from './user-bulk-modify-filters.vue';
import UserBulkModifyNewValues from './user-bulk-modify-new-values.vue';
import FilterEvents from './userBulkModifyFilterEvents';
import UserLogView from "./user-log-view";
import BottomDrawer from '@schoolboxVue/components/bottomDrawer/bottom-drawer';
import footerUI from '@schoolboxVue/components/vueTables2/mixinFooterUI';
import RevealModal from '@schoolboxVue/components/reveal-modal';
import Toast from '@schoolboxVue/components/toast';


export default {

    components: {
        UserBulkModifyFilters,
        UserBulkModifyNewValues,
        BottomDrawer,
        RevealModal,
        Toast,
        UserLogView,
    },

    mixins: [
        footerUI //to place and keep the tables UI in the footer
    ],

    props: {
        srcUserList: {
            type: String,
            required: true
        },
        srcUserModify: {
            type: String,
            required: true
        },
        srcUserDelete: {
            type: String,
            required: true
        },
        campuses: {
            type: Array,
            required: true
        },
        roles: {
            type: Array,
            required: true
        },
        yearLevels: {
            type: Array,
            required: true
        },
        eportfolioEnabled: {
            type: Boolean
        },
        houses: {
            type: Array,
            required: true
        },
    },

    emits: ['displayLog'],

    data () {
        return {
            hasDataLoaded: false, //show loading display until true
            hasLoadError: false, //display load error
            rows: [],
            selectedRows: [], //keep track of which rows could be modified
            tableColumns: ['firstname', 'lastname', 'external_id', 'campuses', 'role', 'year', 'token', 'eportfolio_enabled'],
            tableOptions: {
                customFilters: [ //refer to https://matanya.gitbook.io/vue-tables-2/custom-filters for further info
                    {
                        name: FilterEvents.CAMPUS,
                        callback: (row, query) => {
                            return row.campuses.join().includes(query); //need to flatten the campuses array then check
                        }
                    },
                    {
                        name: FilterEvents.ROLE,
                        callback: (row, query) => {
                            return row.role === query;
                        }
                    },
                    {
                        name: FilterEvents.YEAR,
                        callback: (row, query) => {
                            if (query === 'null') {
                                query = null; //cast to proper null here to match
                            }
                            return row.year === query;
                        }
                    },
                    {
                        name: FilterEvents.TOKEN,
                        callback: (row, query) => {
                            return row.token === query;
                        }
                    },
                    {
                        name: FilterEvents.EPORTFOLIO,
                        callback: (row, query) => {
                            return row.eportfolio_enabled === query;
                        }
                    },
                ],
                customSorting: {
                    year: (ascending) => { //sort year level naturally
                        return (a, b) => {
                            if (ascending) {
                                return this.collator.compare(a.year, b.year);
                            } else {
                                return this.collator.compare(b.year, a.year);
                            }
                        }
                    }
                },
                debounce: 100, //faster debounce as data is stored on the client side,
                headings: {
                    firstname: 'First Name',
                    lastname: 'Surname',
                    external_id: 'External ID',
                    campuses: 'Campuses',
                    role: 'Role',
                    year: 'Year Level',
                    token: 'Token',
                    eportfolio_enabled: 'ePortfolio',
                },
                orderBy: {
                    ascending: false,
                    column: 'Surname' //TODO ??
                },
                perPage: 25,
                perPageValues: [25, 50, 100, 150, 200],
                resizableColumns: false,
                selectable: {
                    mode: 'multiple' //to enable multi-select
                },
                sortIcon: {
                    is: 'glyphicon-sort',
                    base: 'glyphicon',
                    up: 'glyphicon-chevron-up',
                    down: 'glyphicon-chevron-down'
                },
                tabbable: false,
                templates: {
                    campuses: (h, row, index) => {
                        return row.campuses.join(', ');
                    },
                    token: (h, row, index) => {
                        return row.token === '1' ? 'Set' : 'Not set';
                    },
                    eportfolio_enabled: (h, row, index) => {
                        return row.eportfolio_enabled === '1' ? 'Enabled' : 'Disabled';
                    },
                },
                texts: {
                    filterPlaceholder: 'Search for users...',
                    noResults: 'No matching users...',
                    count: '{from}-{to} of {count}',
                    limit: 'Rows per page:'
                },
            },
            singleRowName: 'user',
            pluralRowName: 'users',
            selectContainer: null,
            collator: new Intl.Collator('en', {numeric: true, sensitivity: 'base'}), //to naturally sort year level column
            isShiftKeySelected: false, //to multi-select fields,
            hasSelectedShiftKey: false, //to hide a UI note
            hasDataError: false,
            logData: [], //to display the output of the returned action
        }
    },

    computed: {

        /**
         * To track how many are selected
         *
         * @returns {Number}
         */
        selectedRowsCount () {
            return this.selectedRows.length;
        },

        /**
         * For proper count display, eg: '1 user' / '4 users'
         *
         * @returns {String}
         */
        selectedRowsCountWithRowName () {
            if (this.selectedRowsCount === 0) {
                return 'No ' + this.singleRowName;
            } else if (this.selectedRowsCount === 1) {
                return this.selectedRowsCount + ' ' + this.singleRowName;
            } else {
                return this.selectedRowsCount + ' ' + this.pluralRowName;
            }
        },

    },

    mounted () {
        this.initShiftKeyDetection();
        this.loadRows();
    },

    methods: {

        /**
         * To hide a UI note informing of shift-selection once pressed once
         */
        initShiftKeyDetection () {
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Shift') {
                    this.isShiftKeySelected = true;
                }
            });
            document.addEventListener('keyup', (event) => {
                if (event.key === 'Shift') {
                    this.isShiftKeySelected = false;
                }
            });
        },

        /**
         * Load users
         */
        async loadRows () {
            try {
                const response = await fetch(this.srcUserList, {
                    method: 'POST',
                    body: JSON.stringify(this.getRowsBody()),
                });
                this.rows = await response.json();
            } catch (error) {
                console.log(error);
                this.hasLoadError = true; //display error note in the DOM
            } finally {
                this.hasDataLoaded = true; //update the display to show the list
            }
        },

        /**
         * Pass in the params to return all rows
         *
         * @retrun { Object}
         */
        getRowsBody () {
            return  {
                campusId: -1,
                roleId: -1,
                yearLevelId: -1,
                token: -1,
                ePortfolio: -1,
            }
        },

        /**
         * To update the table to show the UI in the footer
         */
        onLoaded () {
            this.$nextTick(() => this.footerUI_update());
        },

        /**
         * To keep track of selected rows
         */
        onRowSelected (event) {
            this.selectedRows = event;
            if (this.selectedRows.length > 1 && this.isShiftKeySelected) { //to hide a UI note, notifying of availability of shift-select
                this.hasSelectedShiftKey = true;
            }
        },

        /**
         * Clear the selection
         */
        clearRows () {
            this.selectedRows = [];
            this.$refs.table.resetSelectedRows();
        },

        /**
         * To confirm/cancel selected users deletion
         */
        onClearUsersSelected () {
            this.clearRows();
        },

        /**
         * To open the modify modal
         */
        onModifySelected () {
            this.$refs.newValues.modify(); //will open up the reveal-modal in user-bulk-modify-new-values
        },

        /**
         * Selected modify is complete, so clear the rows and update the data
         */
        onUpdated () {
            this.clearRows();
            this.loadRows(); //refresh the data
        },

        /**
         * To confirm/cancel selected users deletion
         */
        onDeleteUsersSelected () {
            this.$refs.deleteModal.open();
        },

        /**
         * Cancel the the selected users deletion
         */
        onCancelDeleteUsersSelected () {
            this.$refs.deleteModal.close();
        },

        /**
         * Delete the selected users
         */
        async onConfirmDeleteUsersSelected () {
            this.$refs.toast.activate('Deleting...');
            const usersToModifyIDList = this.selectedRows.map(row => row.id);
            const formData = new FormData();
            usersToModifyIDList.forEach((item) => {
                formData.append('users[]', item);
            });
            try {
                this.isProcessing = true;
                const response = await fetch(this.srcUserDelete, {
                    headers: {
                        'Accept': 'application/json',
                    },
                    method: 'POST',
                    body: formData,
                });
                const responseData = await response.json();
                this.checkUpdateResponse(response, responseData);
                this.$refs.toast.timeout('Deleted', true, 2000);
                this.$refs.deleteModal.close();

            } catch (error) {
                console.warn(error);
                this.isProcessing = false;
                this.$refs.toast.timeout('Unable to delete', false, 2000);
                this.hasDataError = true;

            } finally {
                this.hasUpdated = true;
                this.isProcessing = false;

                this.$refs.userLogView.logData = this.logData;
                this.$refs.userLogView.hasDataError = this.hasDataError;
                this.$refs.userLogView.onLogOpenSelected();
            }
        },

        /**
         * To update the row display after deletion
         *
         * @returns { Array }
         */
        getUpdatedRowsAfterUserDeletion () {
            return this.rows.filter((row) => {
                if (this.selectedRows.some((rowToDelete) => rowToDelete.id === row.id)) { //pull out any deleted rows, those rows that are in selectedFiles, which have just been deleted
                    return false;
                }
                return true;
            });
        },

        /**
         * Check for any issues in the returned update
         *
         * @param { Object }
         * @param { Object }
         */
        checkUpdateResponse (response, responseData) {
            if (response.ok) { //successful response
                this.hasDataError = false;
                this.logData = responseData;
                this.rows = this.getUpdatedRowsAfterUserDeletion();
                this.clearRows();
                this.$refs.table.resetSelectedRows();
            } else if (!response.ok && response.status === 501) { //otherwise check for 501 error
                this.hasDataError = true;
                this.logData = [responseData.title]; //which comes in with an error message
            } else {
                this.hasDataError = true;
                this.logData = ['Sorry, an unknown error has occurred. Please contact your IT support if the problem persists.'];
                if (responseData.title !== undefined) {
                    this.logData = [responseData.title];
                }
            }
        },

    },

}
</script>

<template>
    <div>
        <div v-if="hasLoadError">
            <section class="content">
                <div tableData-alert class="alert-box alert">
                    Sorry, we are currently unable to load the Bulk Modify list. Please contact your support it the problem persists.
                </div>
            </section>
        </div>
        <v-client-table
            v-else-if="hasDataLoaded"
            ref="table"
            :data="rows"
            :columns="tableColumns"
            :options="tableOptions"
            @loaded="onLoaded"
            @select="onRowSelected"
        >
            <template #beforeTable>
                <user-bulk-modify-filters
                    :campuses="campuses"
                    :roles="roles"
                    :year-levels="yearLevels"
                    :eportfolio-enabled="eportfolioEnabled"
                />
            </template>
            <template #afterTable>
                <div class="VueTables__table-footer" ref="tableFooter"></div>
            </template>
        </v-client-table>
        <div v-else>
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
        <bottom-drawer :showDrawer="selectedRowsCount > 0">
            <h3 class="selected-count"><em>{{ selectedRowsCountWithRowName }} selected</em></h3>
            <span v-if="!hasSelectedShiftKey" class="shift-select-note">Use Shift+Select to select multiple users</span>
            <button @click.prevent="onClearUsersSelected" class="button-clear">Clear User Selection</button>
            <button @click.prevent="onModifySelected" class="button submit">Modify</button>
            <button @click.prevent="onDeleteUsersSelected" class="button submit button-delete">Delete Users</button>
        </bottom-drawer>
        <user-bulk-modify-new-values
            ref="newValues"
            :src-user-modify="srcUserModify"
            :campuses="campuses"
            :roles="roles"
            :year-levels="yearLevels"
            :houses="houses"
            :eportfolio-enabled="eportfolioEnabled"
            :selected-rows="selectedRows"
            :selected-rows-count-with-row-name="selectedRowsCountWithRowName"
            @updated="onUpdated"
        />
        <reveal-modal ref="deleteModal">
            <h2>Please Confirm Multiple User Deletion</h2>
            <div data-alert class="alert-box alert">
                You are about to permanently delete <strong>{{ selectedRowsCountWithRowName }}</strong>. Deleting users
                will prevent them from logging into the system and from accessing resources that belong to them. Attached
                resources that will be removed include; ePortfolio, My Files, Pastoral Records and My Links. Resources shared
                with or by the user like Files, Grades, News, Forum Posts and other collaborative activities will remain
                available.
            </div>
            <ul class="flex-list buttons">
                <li><button @click.prevent="onConfirmDeleteUsersSelected" class="button submit">Confirm Deletion</button></li>
                <li><button @click.prevent="onCancelDeleteUsersSelected" class="button">Cancel</button></li>
            </ul>
        </reveal-modal>

        <User-Log-View ref="userLogView" @display-log="displayLog"/>

        <toast ref="toast"></toast>

    </div>
</template>

<style lang="scss">

@import '@schoolboxVue/components/vueTables2/vueTables2';

.VueTables--client {
    padding-bottom: 100px; // allow space for bottom-drawer at the bottom
}

.selected-count {
    margin-right: 1rem;
}

.button-clear, .selected-count {
    display: inline-block;
}

.shift-select-note {
    color: #9a9a9a;
    margin-right: 1rem;
    font-size: 13px;
    font-style: italic;
}

.button-clear {
    padding: 0px;
    background: none;
    vertical-align: baseline;
    font-size: 0.8rem;
    &:hover {
        background: none;
    }
}

textarea { //to prevent the potential for a massively long modal
    min-height: 200px;
    max-height: 600px;
}

</style>
