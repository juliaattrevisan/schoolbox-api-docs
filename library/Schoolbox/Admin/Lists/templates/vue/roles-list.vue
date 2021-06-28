<script>

import axios from 'axios';
import RolesListHeader from './roles-list-header';
import RolesListConfigureAccess from './roles-list-configure-access';
import RolesListItem from './roles-list-item';
import Toast from '@schoolbox/toast';
import RevealModal from '@schoolbox/reveal-modal';

export default {
    
    components: {
        RolesListHeader,
        RolesListConfigureAccess,
        RolesListItem,
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
        }
    },

    data () {
        return {
            fields: [], //to hold the header fields
            records: [], //to hold initialRecords and manage any further changes
            isConfigureAccessOpen: false, //to open and close the list
            selectedId: -1 //when deleting a row, use the index 
        }
    },

    computed: {

        /**
         * Currently need to wrangle the field display to only show Name and Role type, 
         * the rest are to be shown only when Role Type is set to Staff
         * 
         */
        displayFields () {
            const onlyNameAndRoleTypeFields = this.fields.filter((field, index) => index < 2); //which are the first two fields
            onlyNameAndRoleTypeFields.push( { caption: '', width: '8' } ); //and add another to allow space for Configuring access permissions 
            return onlyNameAndRoleTypeFields;
        },  
        
        /**
         * To provide the access fields
         * 
         */
        accessFields () {
            return this.fields.filter((field, index) => index > 2); //all excluding Name and Type
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
        }

    },

    created () {
        this.fields = Object.values(this.table.fields);
        this.records = Object.values(this.initialRecords);
        
    },

    mounted () {
        this.initSortable();
    },

    methods: {

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
         * Push in a new row as with basic guest access
         * 
         */
        onNewRowSelected () {
            this.records.push(
                {
                    name: '',
                    type: 'guest',
                    canChangePassword: false
                }
            )
        },

        /**
         * Show the access options
         * 
         */
        onConfigureAccessSelected (selectedId) {
            //fade out unselected ids
            this.isConfigureAccessOpen = true;
        },

        /**
         * close the access options
         * 
         */
        onConfigureAccessClosed () {
            //fade in unselected ids
            this.isConfigureAccessOpen = false;
        },

        /**
         * Open the delete modal, storing the selected row index for possible deletion
         * 
         * @param { Number }
         */
        onDeleteRowSelected (selectedId) {
            this.selectedId = selectedId;
            this.$refs.deleteModal.open();
        },

        /**
         * Pull out the selected row index on delete confirmation
         *
         */
        async onConfirmDeleteRowSelected () {
            this.$refs.toast.activate('Deleting row...');
            try {
                const id = this.selectedId + 1;
                const params = { 
                    table: this.tableName,
                    id: id
                }
                const response = await axios.post(this.deleteRoute, params);
                if (response.result !== false) {
                    this.records = this.records.filter((item, index) => index !== this.selectedId);
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

            <form v-else method="post" action="/adminv2/lists/save">

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
                            <roles-list-item 
                                v-for="(record, index) in records"
                                :key="index"
                                :id="index+1"
                                :display-fields="displayFields"
                                :access-fields="accessFields"
                                :record="record"
                                :is-sortable="table.sortable"
                                :is-deletable="table.delete"
                                @on-configure-access-selected="onConfigureAccessSelected"
                                @on-delete-row-selected="onDeleteRowSelected"
                            />
                        </ul>

                        <roles-list-configure-access
                            :access-fields="accessFields" 
                            :is-open="isConfigureAccessOpen" 
                            @on-configure-access-closed="onConfigureAccessClosed"
                        />

                    </div><!-- .list-container -->

                    <div v-if="!table.noNewRecords" class="component-action">
                        <section>
                            <a @click.prevent="onNewRowSelected" href="#add-new-row" data-button="add-row" class="button">Add New Row</a>
                        </section>
                    </div>

                </section>

                <input type="submit" value="Save"/>

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

.list-container {
    position: relative;
}

</style>