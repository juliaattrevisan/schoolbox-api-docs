<script>

/**
 * Bulk modify selected users
 */

import UserBulkModifyFilters from './user-bulk-modify-filters.vue';
import UserBulkModifyNewValues from './user-bulk-modify-new-values.vue';
import FilterEvents from './userBulkModifyFilterEvents';
import RevealModal from '@schoolboxVue/components/reveal-modal';
import BottomDrawer from '@schoolboxVue/components/bottomDrawer/bottom-drawer';
import footerUI from '@schoolboxVue/components/vueTables2/mixinFooterUI';

export default {

    components: {
        UserBulkModifyFilters,
        UserBulkModifyNewValues,
        BottomDrawer,
        RevealModal,
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

    data () {
        return {
            hasRowsLoaded: false,
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
                        return row.token !== null ? 'Set' : 'Not set';
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
            isModifying: false, //to open up the user-bulk-modify-new-values view
            logData: [],
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
                this.hasRowsLoaded = true;
            } catch (error) {
                console.log(error);
                this.hasLoadError = true; //display error note in the DOM
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
        },

        /**
         * To confirm/cancel selected users deletion
         */
        onClearUsersSelected () {
            this.clearRows();
            this.$refs.table.resetSelectedRows();
        },

        /**
         * Clear the selection
         */
        onModifySelected () {
            // this.isModifying = true;
            this.$refs.modal.open();
        },

    },
    
}
</script>

<template>
    <div>
        <v-client-table
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
        <bottom-drawer :showDrawer="selectedRowsCount > 0 && !isModifying">
            <h3 class="selected-count"><em>{{ selectedRowsCountWithRowName }} selected</em></h3>
            <span v-if="!hasSelectedShiftKey" class="shift-select-note">Use Shift+Select to select multiple users</span>
            <button @click.prevent="onClearUsersSelected" class="button-clear">Clear User Selection</button>
            <button @click.prevent="onModifySelected" class="button submit">Modify</button>
        </bottom-drawer>
        <reveal-modal ref="modal" size="xlarge">
            <user-bulk-modify-new-values 
                :campuses="campuses"
                :roles="roles"
                :year-levels="yearLevels"
                :houses="houses"
                :eportfolio-enabled="eportfolioEnabled"
                :selected-rows="selectedRows"
                :is-modifying="isModifying"
            />
            <!-- <div class="row">
                <div class="small-12 island">
                    <section>
                        <div class="content">
                            <p v-html="logData"></p>
                        </div>
                    </section>
                </div>
            </div> -->
        </reveal-modal>
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


</style>
