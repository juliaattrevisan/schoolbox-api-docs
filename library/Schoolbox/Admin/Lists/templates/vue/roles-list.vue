<script>

/**
 * To view and edit the Roles List, currently showing Name and Role Type, with all others accessed via a Permissions popup
 * The columns are refered to as 'fields', with the rows as 'records'
 *
 */

import axios from 'axios';
import RolesListHeader from './roles-list-header';
import RolesListPermissions from './roles-list-permissions';
import RolesListRecord from './roles-list-record';
import Toast from '@schoolbox/toast';
import RevealModal from '@schoolbox/reveal-modal';

export default {
    
    components: {
        RolesListHeader,
        RolesListPermissions,
        RolesListRecord,
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
        initialRecords: { //list of currently active records
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
            records: [], //to display and possibly edit the records 
            isPermissionsOpen: false, //to open and close the list
            selectedRecord: { id: '-1', name: '', type: '' }, //just a place holder until permissions or delete is accessed
            saveStatus: 'Save All' //to update the button text when saving
        }   
    },

    computed: {

        /**
         * Assign a key for each field for future reference, so can link the selectedRecord to the fields when editing permissions
         * 
         * @returns { Array of Objects }
         */
        fields () {
            const fields = Object.values(this.table.fields);
            const recordKeyList = Object.keys(this.records[0]);
            fields.forEach((field, index) => {
                field.key = recordKeyList[index];
            });
            return fields;
        }, 

        /**
         * Only Name and Role type are shown with
         * the rest shown within #roles-list-permissions.vue
         * 
         * @returns { Array of Objects }
         */
        displayFields () {
            const onlyNameAndRoleTypeFields = this.fields.filter((field, index) => index < 2); //which are the first two fields
            onlyNameAndRoleTypeFields.push( { caption: 'Permissions', width: '6' } ); //and add another to allow space for Configuring access permissions 
            return onlyNameAndRoleTypeFields;
        },  
        
        /**
         * To provide the permission fields, pulling out Name and Role Type
         * 
         * @returns { Array of Objects }
         */
        permissionFields () {
            return this.fields.filter((field) => {
                return (field.caption !== 'Name' && field.caption !== 'Role Type'); //all excluding name and type
            });
        }, 

        /**
         * To show list of fields that cannot be null
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

    },

    created () {
        this.initRecords();
    },

    mounted () {
        this.initSortable();
    },

    methods: {

        /**
         * Add in the id so can link to the DB on form submit
         * 
         */
        initRecords () {
            this.records = Object.values(this.initialRecords);
            const recordKeys = Object.keys(this.initialRecords);
            this.records.forEach((record, index) => { 
                record.id = recordKeys[index];
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
         * Push in a new row with basic guest access
         * 
         */
        onNewRowSelected () {
            this.records.push(
                {
                    name: '',
                    type: 'guest',
                    id: '-1',
                    canChangePassword: false
                }
            )
        },

        /**
         * Show the permissions options
         * 
         */
        onPermissionsSelected (selectedRecord) {
            this.selectedRecord = selectedRecord;
            this.isPermissionsOpen = true;
        },

        /**
         * close the permissions options
         * 
         */
        onPermissionsClosed () {
            this.isPermissionsOpen = false;
        },

        /**
         * Open the delete modal, storing the selected row index for possible deletion
         * 
         * @param { Number }
         */
        onDeleteRecord (selectedRecord) {
            this.selectedRecord = selectedRecord;
            if (selectedRecord.id === '-1') { //hasn't been placed int he DB yet, so no need to confirm its removal
                this.records = this.records.filter((record) => record.id !== this.selectedRecord.id);
            } else {
                this.$refs.deleteModal.open();
            }
        },

        /**
         * Pull out the selected row index on delete confirmation
         *
         */
        async onConfirmDeleteRecordSelected () {
            this.$refs.toast.activate('Deleting row...');
            try {
                const params = { 
                    table: this.tableName,
                    id: this.selectedRecord.id
                }
                const response = await axios.post(this.deleteRoute, params);
                if (response.result !== false) {
                    this.records = this.records.filter((record) => record.id !== this.selectedRecord.id);
                    $toast.toastTimeout({text: 'Item deleted', css: 'success'});
                } else {
                    $toast.toastTimeout({text: 'Deletion Failed. ' + response.message, css: 'alert'});
                }

            } catch (error) {
                this.$refs.toast.timeout('Unable to delete', false, 2000);
            } 
            finally {
                this.$refs.deleteModal.close();
            }
        },

        /**
         * Cancel the the selected row deletion
         *
         */
        onCancelDeleteRowSelected () {
            this.$refs.deleteModal.close();
        },

        /**
         * Close the permissions modal
         *
         */
        onClosePermissionsSelected () {
            
        },

        /**
         * Save all permissions
         *
         */
        async onSaveAllSelected () {
            this.saveStatus = 'Saving...'
            this.$refs.toast.activate('Saving row...');
            try {
                const formData = new FormData(this.$refs.form);
                const response = await axios.post(this.saveRoute, formData);
                if (response.status === 200) {
                    this.saveStatus = 'Saved';
                } 
            } catch (error) {
                this.$refs.toast.timeout('Unable to save', false, 2000);
            } 
            finally {
                this.$refs.deleteModal.close();
                this.saveStatus = 'Save All';
            }
        },

    }

}
</script>

<template>

    <div>
        
        <div class="small-12 island">
            
            <div v-if="records.length === 0" id="listEmptyState" class="empty-state">
                <i class="icon-list"></i>
                <p>The {{ table.title }} list is empty.<br><a href="#" data-button="add-row">Add an item to this list</a>.</p>
            </div>

            <form v-else method="post" action="/adminv2/lists/save" ref="form">

                <input name="table" type="hidden" :value="tableName" />

                <!-- {# This is used when hidden fields cannot be NULL. Existing records should already be set in DB #} -->
                <input v-for="(field, index) in fixedList" :key="index" type="hidden" :name="`fixed-${field.key}`" :value="field.value" />

                <section id="listEditor">

                    <div class="list-container">

                        <roles-list-header 
                            :display-fields="displayFields"
                            :is-sortable="table.sortable"
                            :is-deletable="table.delete"
                        />

                        <!-- {% set ulAttr = sortable ? 'class="information-list sortable ui-sortable" data-sortable data-list' : 'class="information-list unsortable" data-list' %} --> 
                        <ul :class="`information-list ${ table.sortable ? 'sortable ui-sortable' : 'unsortable'}`" data-sortable data-list>
                            <roles-list-record 
                                v-for="(record, index) in records"
                                :key="index"
                                :display-fields="displayFields"
                                :permission-fields="permissionFields"
                                :record="record"
                                :selected-record="selectedRecord"
                                :is-permissions-open="isPermissionsOpen"
                                :is-sortable="table.sortable"
                                :is-deletable="table.delete"
                                @on-permissions-selected="onPermissionsSelected"
                                @on-delete-row-selected="onDeleteRecord"
                            />
                        </ul>

                        <roles-list-permissions
                            :permission-fields="permissionFields" 
                            :selected-record="selectedRecord"
                            :is-open="isPermissionsOpen" 
                            @on-permissions-closed="onPermissionsClosed"
                        />

                        <div v-if="!table.noNewRecords" class="component-action">
                            <section>
                                <a @click.prevent="onNewRowSelected" href="#add-new-row" data-button="add-row" class="button">Add New Row</a>
                            </section>
                        </div>

                    </div><!-- .list-container -->

                </section>

                <input type="submit" @click.prevent="onSaveAllSelected" :value="saveStatus" />
                <!-- <input type="submit" value="Save All" /> -->

            </form>

        </div><!-- small-12 island -->  

        <toast ref="toast"></toast>

        <reveal-modal ref="deleteModal">
            <h2>Please Confirm</h2>
            <div data-alert class="alert-box warning">{{ table.deleteWarning }}</div>
            <ul class="flex-list buttons">
                <li><a @click.prevent="onConfirmDeleteRecordSelected" class="button submit">Yes</a></li>
                <li><a @click.prevent="onCancelDeleteRowSelected" class="button">Cancel</a></li>
            </ul>
        </reveal-modal>

    </div>

</template>

<style lang="scss" scoped>

.list-container {
    position: relative;
}

</style>