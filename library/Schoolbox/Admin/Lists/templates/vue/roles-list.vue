<script>

import axios from 'axios';
import { initDraggable } from '@shared/dragger/dragger';
import RolesListHeader from './roles-list-header';
import RolesListItem from './roles-list-item';
import Toast from '@schoolbox/toast';
import RevealModal from '@schoolbox/reveal-modal';

export default {
    
    components: {
        RolesListHeader,
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
            rowIndexToDelete: -1 //when deleting a row, use the index 
        }
    },

    computed: {

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
        this.initDraggable();
    },

    methods: {

        /**
         * Init the drag feature
         * 
         */
        initDraggable () {
            if (this.table.sortable) {
                $('[data-sortable]').sortable({ handle: '.grip', axis: 'y' });
            }
        },

        /**
         * Push in a new row 
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
         * Open the delete modal, storing the selected row index for possible deletion
         * 
         * @param { Number }
         */
        onDeleteRowSelected (rowIndexToDelete) {
            this.rowIndexToDelete = rowIndexToDelete;
            this.$refs.deleteModal.open();
        },

        /**
         * Pull out the selected row index on delete confirmation
         *
         */
        async onConfirmDeleteRowSelected () {
            this.$refs.toast.activate('Deleting row...');
            try {
                const id = this.rowIndexToDelete + 1;
                const params = { 
                    table: this.tableName,
                    id: id
                }
                const response = await axios.post(this.deleteRoute, params);
                if (response.result !== false) {
                    this.records = this.records.filter((item, index) => index !== this.rowIndexToDelete);
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

                    <roles-list-header 
                        :fields="fields"
                        :is-sortable="table.sortable"
                        :is-deletable="table.delete"
                    />

                    <!-- {% set ulAttr = sortable ? 'class="information-list sortable ui-sortable" data-sortable data-list' : 'class="information-list unsortable" data-list' %} -->
                    <ul :class="`information-list ${ table.sortable ? 'sortable ui-sortable' : 'unsortable'}`" data-sortable data-list>
                        <roles-list-item 
                            v-for="(record, index) in records"
                            :key="index"
                            :fields="fields"
                            :record="record"
                            :is-sortable="table.sortable"
                            :is-deletable="table.delete"
                            @on-delete-row-selected="onDeleteRowSelected"
                        />
                    </ul>

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

<style lang="sass">

</style>