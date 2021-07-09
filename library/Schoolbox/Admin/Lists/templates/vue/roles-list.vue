<script>

/**
 * To view and edit the Roles List, currently showing Name and Role Type, with all others accessed via a Permissions popup
 *
 */

import axios from 'axios';
import { gsap } from 'gsap/all'
import RolesListHeader from './roles-list-header';
import RolesListPermissions from './roles-list-permissions';
import RolesListRow from './roles-list-row';
import Toast from '@schoolbox/toast';
import RevealModal from '@schoolbox/reveal-modal';

export default {
    
    components: {
        RolesListHeader,
        RolesListPermissions,
        RolesListRow,
        Toast,
        RevealModal,
    },

    props: {
        tableName: { //the name of the current list, to pass through the form submit
            type: String,
            required: true
        },
        table: { //all the table data, including warning messages, title etc
            type: Object,
            required: true
        },
        initialRows: { //list of currently active rows
            type: Object,
            required: true
        },
        deleteRoute: { //url of row delete
            type: String,
            required: true
        },
        saveRoute: { //url of entire list save
            type: String,
            required: true
        }
    },

    data () {
        return {
            rows: [], //to display and possibly edit the rows 
            isPermissionsOpen: false, //to open and close the list
            selectedRow: { id: '-1', name: '', type: '' }, //just a place holder until permissions or delete is accessed
            saveStatus: 'Save All', //to update the button text when saving
            newId: 0, //to add another row to the db, decrement with each new row added,
            selectedPermissionName: '', //to highlight all other rows that have that permission
            isProcessing: false, //to prevent multiple rapid-fire submissions
            isSaving: false,
        }   
    },

    computed: {

        /**
         * Assign a key for each column for future reference, so can link the selectedRow to the columns when editing permissions
         * 
         * @returns { Array of Objects }
         */
        columns () {
            const columns = Object.values(this.table.fields);
            const rowKeysList = Object.keys(this.rows[0]);
            columns.forEach((column, index) => {
                column.key = rowKeysList[index];
            });
            return columns;
        }, 

        /**
         * Only Name and Role type are shown with
         * the rest within #roles-list-permissions.vue
         * 
         * @returns { Array of Objects }
         */
        headerColumns () {
            const headerColumns = this.columns.filter((column) => {
                return (column.caption === 'Name' || column.caption === 'Role Type'); 
            }); 
            headerColumns.push( { //and add another to allow space for Permissions UI
                caption: 'Permissions', 
                width: '8', 
                tooltip: 'The permissions each row is currently granted. Select a permission below to see all rows which also have the selected permission.' 
            } ); 
            return headerColumns;
        },  
        
        /**
         * To provide the permission columns, pulling out Name and Role Type
         * 
         * @returns { Array of Objects }
         */
        permissionColumns () {
            return this.columns.filter((column) => {
                return (column.caption !== 'Name' && column.caption !== 'Role Type' && column.caption !== 'sequence'); //all excluding Name, Role Type and sequence
            });
        }, 

        /**
         * To show list of columns that cannot be null
         * 
         * @returns { Array of Objects }
         */
        fixedList () {
            const fixedList = [];
            for (const [key, value] of Object.entries(this.table.fixed)) {
                fixedList.push({ key: key, value: value });
            }
            return fixedList;
        },

        /**
         * To display the rows how they have been sorted
         * 
         * @returns { Array of Objects }
         */
        rowsSortedBySequence () {
            return this.rows.slice().sort((a, b) => a.sequence - b.sequence);
        },

    },

    created () {
        this.initRows();
    },

    mounted () {
        this.initSortable();
    },

    methods: {

        /**
         * Add in the id so can link to the DB on form submit
         * and a boolean to know whether the row is one being newly added
         * 
         */
        initRows () {
            this.rows = Object.values(this.initialRows);
            const rowKeys = Object.keys(this.initialRows);
            this.rows.forEach((row, index) => { 
                row.id = rowKeys[index]; //for example 1: { name: 'Teacher' } - so retrieve the key of 1 which is the id to link with DB
                row.isNew = false;
            });
        },

        /**
         * Init the sortable feature
         * 
         */
        initSortable () {
            if (this.table.sortable) {
                $('[data-sortable]').sortable({ handle: '.grip', axis: 'y' });
            }
        },

        /**
         * Show the permissions options
         * 
         */
        onPermissionsSelected (selectedRow) {
            this.selectedRow = selectedRow;
            this.isPermissionsOpen = true;
            document.querySelector('[data-sortable]').style.pointerEvents = 'none'; //prevent ediding/sorting while permissions is open
        },

        /**
         * Close the permissions options
         * 
         */
        onPermissionsClosed () {
            this.isPermissionsOpen = false;
            document.querySelector('[data-sortable]').style.pointerEvents = 'auto';
        },

        /**
         * Push in a new row with basic guest access
         * 
         */
        onNewRowSelected () {
            this.rows.push(
                {
                    name: '',
                    type: 'guest',
                    canChangePassword: true,
                    viewStudentProfile: false,
                    viewStudentGrades: false,
                    viewStudentCalendar: false,
                    viewParentProfile: false,
                    accessEportfolio: false,
                    accessPastoral: false,
                    createBadges: false,
                    createEvidence: false,
                    viewParentDirectory: false,
                    viewStaffDirectory: false,
                    sequence: this.getNextSequence(),
                    id: this.getNextId(),
                    isNew: true,
                }
            );
        },

        /**
         * The new row is placed at the bottom of the list, so it'll 
         * be the last number in the sequence
         * 
         * @param { Number }
         */
        getNextSequence () {
            return this.rows.length + 1;
        },

        /**
         * Retrieve the lowest id and decrement for it to 
         * be used for the new row
         * 
         * @param { String }
         */
        getNextId () {
            this.newId--;
            return this.newId.toString();
        },

        /**
         * Open the delete modal, storing the selected row index for possible deletion
         * 
         * @param { Number }
         */
        onDeleteRow (selectedRow) {
            this.selectedRow = selectedRow;
            if (selectedRow.isNew) { //hasn't been placed int he DB yet, so no need to confirm its removal
                this.rows = this.rows.filter((row) => row.id !== this.selectedRow.id);
            } else {
                this.$refs.deleteModal.open();
            }
        },

        /**
         * Notify the DB and pull out the selectedRow 
         * on delete confirmation
         *
         */
        async onConfirmDeleteRowSelected () {
            this.isProcessing = true;
            this.$refs.toast.activate('Deleting row...');
            try {
                const response = await axios.post(this.deleteRoute, this.getDeleteRowFormData());
                if (response.result !== false) {
                    this.rows = this.rows.filter((row) => row.id !== this.selectedRow.id);
                    this.$refs.toast.timeout('Row Deleted', true, 2000);
                } else {
                    this.$refs.toast.timeout('Unable to delete', false, 2000);
                }
            } catch (error) {
                this.$refs.toast.timeout('Unable to delete', false, 2000);
            } finally {
                this.$refs.deleteModal.close();
                this.isProcessing = false;
            }
        },

        /**
         * Cancel the the selected row deletion
         *
         */
        getDeleteRowFormData () {
            const formData = new FormData();
            formData.append('table', this.tableName);
            formData.append('id', this.selectedRow.id);
            return formData;
        },

        /**
         * Cancel the the selected row deletion
         *
         */
        onCancelDeleteRowSelected () {
            this.$refs.deleteModal.close();
        },

        /**
         * Save all data
         *
         */
        async onSaveAllSelected () {
            this.isSaving = true;
            this.updateSequenceOrder();
            this.isProcessing = true;
            this.saveStatus = 'Saving...';
            this.$refs.toast.activate('Saving...');
            try {
                const formData = new FormData(this.$refs.form);
                const response = await axios.post(this.saveRoute, formData);
                if (response.status === 200) {
                    this.saveStatus = 'Save All';
                    this.$refs.toast.timeout('Save successful', true, 2000);
                } else {
                    this.$refs.toast.timeout('Unable to save', false, 2000);
                }
            } catch (error) {
                this.$refs.toast.timeout('Unable to save', false, 2000);
            } finally {
                location.reload();
            }
        },

        /**
         * Ensure the correct sorting order is reflected for page reload
         *
         */
        updateSequenceOrder () {
            const allRowElements = document.querySelector('[data-list]').children;
            Array.from(allRowElements).forEach( (rowElement, index) => {
                const rowId = rowElement.dataset.rowId;
                const matchingRowData = this.rows.find((row) => Number(row.id) === Number(rowId));
                matchingRowData.sequence = ++index;
            });
        },

        /**
         * animate settings to help context when filtering
         * refer to https://vuejs.org/v2/guide/transitions.html#Staggering-List-Transitions
         * what happens before shown
         *
         * @param {DOMElement} element - the element that's animating
         *
         */
        onBeforeEnter (row) {
            if (!this.isSaving) {
                gsap.set(row, { height: 0 } );
            }
        },

        /**
         * how it element animates on
         *
         * @param {DOMElement} element - the element that's animating
         * @param {Function} done - to be called once animation is complete
         *
         */
        onEnter (row, done) {
            if (this.isSaving) {
                done();
            } else {
                gsap.to(row, 0.3, { height: 'auto', onComplete: done } );
            }
        },

        /**
         * how the element animates off
         *
         * @param {DOMElement} element - the element that's animating
         * @param {Function} done - to be called once animation is complete
         *
         */
        onLeave (row, done) {
            if (this.isSaving) {
                done();
            } else {
                gsap.to(row, 0.3, { height: 0, onComplete: done } );
            }
        },

        /**
         * To highlight all rows which also have the same permission
         * maybe an easy way to check which rows have the permission?
         * 
         */
        onPermissionNameSelected (permissionName) {
            if (this.selectedPermissionName === permissionName) { //so can toggle on/off
                this.selectedPermissionName = '';
            } else {
                this.selectedPermissionName = permissionName;
            }
            
        }

    }

}
</script>

<template>

    <div>
        
        <div class="small-12 island">
            
            <div v-if="rows.length === 0" id="listEmptyState" class="empty-state">
                <i class="icon-list"></i>
                <p>The {{ table.title }} list is empty.<br><a href="#" data-button="add-row">Add an item to this list</a>.</p>
            </div>

            <form v-else method="post" action="/adminv2/lists/save" ref="form">

                <input name="table" type="hidden" :value="tableName" />

                <!-- {# This is used when hidden columns cannot be NULL. Existing rows should already be set in DB #} -->
                <input v-for="(column, index) in fixedList" :key="index" type="hidden" :name="`fixed-${column.key}`" :value="column.value" />

                <section id="listEditor">

                    <div class="list-container">

                        <roles-list-header 
                            :header-columns="headerColumns"
                            :is-sortable="table.sortable"
                            :is-deletable="table.delete"
                        />

                        <transition-group
                            name="staggered-fade"
                            tag="ul"
                            :class="`information-list ${ table.sortable ? 'sortable ui-sortable' : 'unsortable'}`"
                            v-bind:css="false"
                            data-sortable 
                            data-list 
                            @before-enter="onBeforeEnter"
                            @enter="onEnter"
                            @leave="onLeave"
                        >    
                            <roles-list-row
                                v-for="(row, index) in rowsSortedBySequence"
                                :key="`${row.id}-${index}`"
                                :initial-index="index + 1"
                                :header-columns="headerColumns"
                                :permission-columns="permissionColumns"
                                :row="row"
                                :selected-row="selectedRow"
                                :is-permissions-open="isPermissionsOpen"
                                :selected-permission-name="selectedPermissionName"
                                :is-sortable="table.sortable"
                                :is-deletable="table.delete"
                                @on-permissions-selected="onPermissionsSelected"
                                @on-permission-name-selected="onPermissionNameSelected"
                                @on-delete-row-selected="onDeleteRow"
                            />
                        </transition-group>

                        <roles-list-permissions
                            :permission-columns="permissionColumns" 
                            :selected-row="selectedRow"
                            :is-open="isPermissionsOpen" 
                            :save-route="saveRoute" 
                            @on-permissions-closed="onPermissionsClosed"
                        />

                        <div v-if="!table.noNewRecords" class="component-action">
                            <section>
                                <a @click.prevent="onNewRowSelected" href="#add-new-row" data-button="add-row" class="button" :disabled="isProcessing">Add New Row</a>
                            </section>
                        </div>

                    </div><!-- .list-container -->

                </section>

                <input type="submit" @click.prevent="onSaveAllSelected" :value="saveStatus" :disabled="isProcessing" />

            </form>

        </div><!-- small-12 island -->  

        <toast ref="toast"></toast>

        <reveal-modal ref="deleteModal">
            <h2>Please Confirm</h2>
            <div data-alert class="alert-box warning">{{ table.deleteWarning }}</div>
            <ul class="flex-list buttons">
                <li><a @click.prevent="onConfirmDeleteRowSelected" class="button submit">Yes</a></li>
                <li><a @click.prevent="onCancelDeleteRowSelected" class="button">Cancel</a></li>
            </ul>
        </reveal-modal>

    </div>

</template>

<style lang="scss" scoped>

.list-container { //so roles aren't shown when animating on/off on add/remove
    position: relative;
    overflow: hidden;
}

.information-list {
    position: relative;
}

.component-action {
    position: relative;
}

</style>