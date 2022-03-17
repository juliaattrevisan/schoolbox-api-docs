<script>

/**
 * Bulk modify selected users
 */

import RevealModal from '@schoolboxVue/components/reveal-modal';
import BottomDrawer from '@schoolboxVue/components/bottomDrawer/bottom-drawer';
import footerUI from '@schoolboxVue/components/vueTables2/mixinFooterUI';

export default {

    components: {
        BottomDrawer,
        RevealModal
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
        }
    },

    data () {
        return {
            selectedFilters: {
                campusId: -1,
                roleId: -1,
                ePortfolio: -1,
                yearLevelId: -1,
                token: -1,
            },
            updateFields: {
                campusIds: [],
                roleId: -1,
                yearLevelId: -1,
                houseId: -1,
                ePortfolio: -1,
                emailFromUserName: -1,
                externalMailClient: -1,
                webmail: -1,
                accountEnabled: -1,
                hideContacts: -1,
                hideTimetable: -1,
                positionTitle: '',
                displayGradeGraph: -1,
                revokeToken: -1,
            },
            hasRowsLoaded: false,
            rows: [],
            selectedRows: [], //keep track of which rows could be modified
            columns: ['firstname', 'lastname', 'external_id', 'campuses', 'role', 'year', 'token'],
            options: {
                headings: {
                    firstname: 'First Name',
                    lastname: 'Surname',
                    external_id: 'External ID',
                    campuses: 'Campuses',
                    role: 'Role',
                    year: 'Year Level',
                    token: 'Token',
                },
                orderBy: {
                    ascending: false,
                    column: 'Surname' //TODO ??
                },
                templates: {
                    campuses: (h, row, index) => {
                        return row.campuses.join(', ');
                    }
                },
                perPage: 50,
                perPageValues: [50, 100, 200],
                selectable: {
                    mode: 'multiple' //to enable multi-select
                },
                debounce: 100, //faster debounce as data is stored on the client side,
                resizableColumns: false,
                tabbable: false,
                texts: {
                    filterPlaceholder: 'Search for users...',
                    noResults: 'No matching users...',
                    count: '{from}-{to} of {count}',
                    limit: 'Rows per page:'
                },
                sortIcon: {
                    is: 'glyphicon-sort',
                    base: 'glyphicon',
                    up: 'glyphicon-chevron-up',
                    down: 'glyphicon-chevron-down'
                },
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
                }
            },
            singleRowName: 'user',
            pluralRowName: 'users',
            selectContainer: null,
            collator: new Intl.Collator('en', {numeric: true, sensitivity: 'base'}), //to naturally sort year level column
            isShiftKeySelected: false, //to multi-select fields,
            hasSelectedShiftKey: false, //to hide a UI note
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
         * to hide a UI note informing of shift-selection once pressed once
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
         * load users
         */
        async loadRows () {
            try {
                const response = await fetch(this.srcUserList, {
                    method: 'POST',
                    body: JSON.stringify(this.selectedFilters),
                    // body: this.$refs.filter.data
                });
                this.rows = await response.json();
                this.hasRowsLoaded = true;
            } catch (error) {
                console.log(error);
                this.hasLoadError = true; //display error note in the DOM
            }
        },

        /**
         * To update the table to show the UI in the footer
         */
        onLoaded () {
            this.$nextTick(() => this.footerUI_update());
        },

        /**
         * Notify state change
         *
         * @param { Object }
         */
        onSelectionChange () {
            this.loadRows();
        },

        /**
         * to keep track of selected rows
         */
        onRowSelected (event) {
            this.selectedRows = event;
            if (this.selectedRows.length > 1 && this.isShiftKeySelected) { //to hide a UI note, notifying of availability of shift-select
                this.hasSelectedShiftKey = true;
            }
        },

        /**
         * clear the selection
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
         * Update the selected users
         */
        async onUpdateUsersSelected () {
            // fetch(this.srcUserModify, {
            //     method: 'POST',
            //     body: this.getFormData(),
            // }).then((response) => response.json())
            //     .then((data) => {
            //             this.logData = data;
            //             this.$refs.modal.open();
            //             this.clearRows();
            //         }
            //     ).catch((error) => {
            //     console.log(error);
            // })
            try {
                const response = await fetch(this.srcUserModify, {
                    method: 'POST',
                    body: this.getFormData(),
                });
                const responseData = await response.json();
                this.logData = responseData;
                this.$refs.modal.open();
            } catch (error) {
                console.warn(error);
            } finally {
                this.clearRows();
            }
        },

        /**
         * Set up updating fields to POST
         *
         * @returns {FormData}
         */
        getFormData () {
            const usersToModifyIDList = this.selectedRows.map(row => row.id);
            const formData = new FormData();
            usersToModifyIDList.forEach((item) => {
                formData.append('users[]', item);
            });
            for (const [key, value] of Object.entries(this.updateFields)) {
                formData.append(key, value);
            }
            return formData;
        },

    },
    
}
</script>

<template>
    <div>
        <v-client-table
            ref="table"
            :data="rows"
            :columns="columns"
            :options="options"
            @loaded="onLoaded"
            @select="onRowSelected"
        >
            <template #beforeTable>
                <fieldset class="content">
                    <div class="small-12 columns">
                        <p>
                            Search for users with the criteria listed below, and apply changes to selected users.
                        </p>
                    </div>

                    <div class="small-12 medium-6 large-3 columns">
                        <label>
                            Campus
                            <select @change="onSelectionChange" v-model="selectedFilters.campusId">
                                <option value="-1">Any</option>
                                <option v-for="campus in campuses" :value="campus.id" :key="campus.id">
                                    {{ campus.name }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <div class="small-12 medium-6 large-3 columns">
                        <label>
                            Role
                            <select @change="onSelectionChange" v-model="selectedFilters.roleId">
                                <option value="-1">Any</option>
                                <option v-for="role in roles" :value="role.id" :key="role.id">
                                    {{ role.name }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <div v-if="eportfolioEnabled" class="small-12 medium-6 large-2 columns">
                        <label>
                            ePortfolio
                            <select @change="onSelectionChange" v-model="selectedFilters.ePortfolio">
                                <option value="-1">Any</option>
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </label>
                    </div>

                    <div class="small-12 medium-6 large-2 columns">
                        <label>
                            Year
                            <select @change="onSelectionChange" v-model="selectedFilters.yearLevelId">
                                <option value="-1">Any</option>
                                <option value="">Not set</option>
                                <option v-for="yearLevel in yearLevels" :value="yearLevel.id" :key="yearLevel.id">
                                    {{ yearLevel.name }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <div class="small-12 medium-6 large-2 columns">
                        <label>
                            Token
                            <select @change="onSelectionChange" v-model="selectedFilters.token">
                                <option value="-1">Any</option>
                                <option value="1">Set</option>
                                <option value="0">Not set</option>
                            </select>
                        </label>
                    </div>
                </fieldset>
            </template>
            <template #afterTable>
                <div class="VueTables__table-footer" ref="tableFooter"></div>
            </template>
        </v-client-table>

        <bottom-drawer :showDrawer="selectedRowsCount > 0">
            <h3 class="selected-count"><em>{{ selectedRowsCountWithRowName }} selected</em></h3>
            <span v-if="!hasSelectedShiftKey" class="shift-select-note">Use Shift+Select to select multiple users</span>
            <button @click.prevent="onClearUsersSelected" class="button-clear">Clear User Selection</button>
            <section class="content">
                <div class="row">
                    <div class="small-12 columns">
                        <h3 class="subheader">New Values</h3>
                    </div>
                    <div data-applycampus="true" class="small-6 medium-3 columns">
                        <label>
                            Campus Involvement
                            <span data-tooltip class="hint has-tip tip-top"
                                  title="One or more campuses that the users will be involved in.
                                  Leave blank if you don't want to update their campuses.">?</span>
                        </label>
                        <ul class="option-list">
                            <li v-for="campus in campuses">
                                <input :id="'apply_campus-' + String(campus.id)" name='apply_campus[]'
                                       :value=campus.id v-model="updateFields.campusIds" type="checkbox"/>
                                <label class="checkbox-label"
                                       :for="'apply_campus-' + String(campus.id)">{{ campus.name }}</label>
                            </li>
                        </ul>
                    </div>

                    <div class="small-6 medium-3 columns">
                        <label for="apply_role">Role</label>
                        <select id="apply_role" name="apply_role" v-model="updateFields.roleId">
                            <option value="-1">Unchanged</option>
                            <option v-for="role in roles" :value="role.id">{{ role.name }}</option>
                        </select>
                    </div>

                    <div class="small-6 medium-3 columns">
                        <label for="apply_year_id">
                            Year
                        </label>
                        <select id="apply_year_id" name="apply_year_id" v-model="updateFields.yearLevelId">
                            <option value="-1" selected="selected">Unchanged</option>
                            <option value="">Not set</option>
                            <option v-for="yearLevel in yearLevels" :value="yearLevel.id">{{ yearLevel.name }}</option>
                        </select>
                    </div>

                    <div class="small-6 medium-3 columns">
                        <label for="apply_position_title">
                            Position Title
                            <span data-tooltip class="hint has-tip tip-top"
                                  title="Leave blank if you don't want to update their position title.">?</span>
                        </label>
                        <input id="apply_position_title" name="apply_position_title" type="text"
                               v-model="updateFields.positionTitle">
                    </div>

                    <div class="small-6 medium-3 columns">
                        <label for="apply_house_id">
                            House
                            <span data-tooltip class="hint has-tip tip-top"
                                  title="Leave blank if you don't want to update their house.">?</span>
                        </label>
                        <select id="apply_house_id" name="apply_house_id" v-model="updateFields.houseId">
                            <option value="-1" selected="selected">Unchanged</option>
                            <option value="">Not set</option>
                            <option v-for="house in houses" :value="house.id">{{ house.name }}</option>
                        </select>
                    </div>

                    <div class="small-6 medium-3 columns">
                        <label for="apply_eportfolio">ePortfolio</label>
                        <select id="apply_eportfolio" name="apply_eportfolio" v-model="updateFields.ePortfolio"
                                :disabled="!eportfolioEnabled">
                            <option value="-1">Unchanged</option>
                            <option value="1">Enabled</option>
                            <option value="0">Disabled</option>
                        </select>
                    </div>

                    <div class="small-6 medium-3 columns">
                        <label for="apply_emailfromusername">Email Address From Username</label>
                        <select id="apply_emailfromusername" name="apply_emailfromusername"
                                v-model="updateFields.emailFromUserName">
                            <option value="-1">Unchanged</option>
                            <option value="1">Enabled</option>
                            <option value="0">Disabled</option>
                        </select>
                    </div>

                    <div class="small-6 medium-3 columns">
                        <label for="apply_externalmailclient">Use External Mail Client</label>
                        <select id="apply_externalmailclient" name="apply_externalmailclient"
                                v-model="updateFields.externalMailClient">
                            <option value="-1">Unchanged</option>
                            <option value="1">Enabled</option>
                            <option value="0">Disabled</option>
                        </select>
                    </div>

                    <div class="small-6 medium-3 columns">
                        <label for="apply_webmail">Enable Webmail Tab</label>
                        <select id="apply_webmail" name="apply_webmail" v-model="updateFields.webmail">
                            <option value="-1">Unchanged</option>
                            <option value="1">Enabled</option>
                            <option value="0">Disabled</option>
                        </select>
                    </div>

                    <div class="small-6 medium-3 columns">
                        <label for="apply_accountenabled">Account Enabled</label>
                        <select id="apply_accountenabled" name="apply_accountenabled"
                                v-model="updateFields.accountEnabled">
                            <option value="-1">Unchanged</option>
                            <option value="1">Enabled</option>
                            <option value="0">Disabled</option>
                        </select>
                    </div>

                    <div class="small-6 medium-3 columns">
                        <label for="apply_hidecontacts">Hide Contact Details</label>
                        <select id="apply_hidecontacts" name="apply_hidecontacts" v-model="updateFields.hideContacts">
                            <option value="-1">Unchanged</option>
                            <option value="1">Enabled</option>
                            <option value="0">Disabled</option>
                        </select>
                    </div>

                    <div class="small-6 medium-3 columns end">
                        <label for="apply_hidetimetable">Hide Timetable</label>
                        <select id="apply_hidetimetable" name="apply_hidetimetable"
                                v-model="updateFields.hideTimetable">
                            <option value="-1">Unchanged</option>
                            <option value="1">Enabled</option>
                            <option value="0">Disabled</option>
                        </select>
                    </div>

                    <div class="small-6 medium-3 columns end">
                        <label for="apply_displaygradegraph">Display Comparative Graph on Grades</label>
                        <select id="apply_displaygradegraph" name="apply_displaygradegraph"
                                v-model="updateFields.displayGradeGraph">
                            <option value="-1">Unchanged</option>
                            <option value="1">Enabled</option>
                            <option value="0">Disabled</option>
                        </select>
                    </div>

                    <div class="small-6 medium-3 columns">
                        <label for="revoke_token">Revoke Token</label>
                        <select id="revoke_token" name="revoke_token" v-model="updateFields.revokeToken">
                            <option value="-1">Unchanged</option>
                            <option value="1">Revoke</option>
                        </select>
                    </div>
                </div>
            </section>

            <button @click.prevent="onUpdateUsersSelected" class="button submit button-update">Update Users</button>
        </bottom-drawer>
        <reveal-modal ref="modal">
            <div class="row">
                <div class="small-12 island">
                    <section>
                        <div class="content">
                            <p v-html="logData"></p>
                        </div>
                    </section>
                </div>
            </div>
        </reveal-modal>
    </div>
</template>

<style lang="scss">

@import '@schoolboxVue/components/vueTables2/vueTables2';

#stale-users-report {
    position: relative;
}

.VueTables--client {
    padding-bottom: 100px; // allow space for bottom-drawer at the bottom
}

.selected-count {
    margin-right: 1rem;
}

.button-clear, .button-update, .selected-count {
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
}

</style>
