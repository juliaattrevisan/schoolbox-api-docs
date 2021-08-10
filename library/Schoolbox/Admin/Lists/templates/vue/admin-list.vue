<script>

/**
 * Provide access to CRUD Admin > Lists
 *
 */

import axios from 'axios';
import { gsap } from 'gsap/all'

import AdminListHeader from './admin-list-header';
import AdminListPopOut from './admin-list-pop-out';
import AdminListRow from './admin-list-row';
import Toast from '@vue/components/toast';
import RevealModal from '@vue/components/reveal-modal';

export default {

    components: {
        AdminListHeader,
        AdminListPopOut,
        AdminListRow,
        Toast,
        RevealModal,
    },

    props: {
        tableName: { //the name of the current list, used to submit updates to the DB
            type: String,
            required: true
        }
    },

    data () {
        return {
            isDataLoaded: false, 
            hasDataLoadError: false, 
            data: null, //contains the loaded data
            rows: [], //to display and possibly edit the rows 
            isPopOutOpen: false, //to open and close the list
            popOutHeight: 0, //so the popout 
            selectedRow: {}, //to track which row is selected
            saveStatus: 'Save', //to update the button text when saving
            newId: 0, //to add another row to the db, decrement with each new row added,
            selectedNameInPopOut: '', //to highlight all other rows that have that permission
            isProcessing: false, //to prevent multiple rapid-fire submissions
            isSaving: false,
        }
    },

    computed: {

        /**
         * Retrieve only those fields that will appear in the header
         * 
         * @returns { Array of Objects }
         */
        headerFields () {
            return this.data.fields.filter((field) => field.hasOwnProperty('width')); //use width to determine if it's to display in the header
        },  

        /**
         * Whether the admin-list-pop-out.vue is requred
         * 
         * @returns { Boolean }
         */
        hasPopOut () {
            return this.data.fields.some((field) => field.hasOwnProperty('isPopOutList')); 
        }, 

        /**
         * the popOut field that's used in the header, and is the reference for 
         * the following popOutFields
         * 
         * @returns { Object }
         */
        popOutHeaderField () {
            const popOutHeaderField = this.headerFields.find((field) => field.isPopOutList); //use width to determine if it's to display in the header
            if (typeof popOutHeaderField !== 'undefined') {
                return popOutHeaderField;
            } 
            return {};
        }, 
        
        /**
         * Just those fields that are used in a popoutList
         * 
         * @returns { Array of Objects }
         */
        popOutFields () {
            return this.data.fields.filter((field) => field.popOutList);
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

    mounted () {
        this.getData();
    },

    methods: {

        /**
         * Load the list data relevant to the current list
         */
        async getData () {
            try {
                const response = await axios.get(`/adminv2/lists/data/${this.tableName}`);
                this.data = response.data; 
                this.initRows();
            } catch (error) {
                this.hasDataLoadError = true; //show load error
            } finally {
                this.isDataLoaded = true; //update the display to show the list
                this.initSortable();
            }
        },

        /**
         * Add a boolean to know whether the row is one being newly added
         */
        initRows () {
            this.rows = _.cloneDeep(this.data.rows);
            this.rows.forEach((row) => { 
                row.isNew = false;
            });
        },

        /**
         * Init the sortable feature
         */
        initSortable () {
            if (this.data.isSortable) {
                this.$nextTick(() => {
                    $('[data-sortable]').sortable({ handle: '.grip', axis: 'y' });
                });
            }
        }, 

        /**
         * Ensure there's enough height in the container so the pop out doesn't get cut off
         */
        onPopOutMounted (popOutHeight) {
            this.popOutHeight = popOutHeight;
        },

        /**
         * Show the popout options
         */
        onPopOutSelected (selectedRow) {
            this.selectedRow = selectedRow;
            this.isPopOutOpen = true;
            document.querySelector('[data-list]').style.pointerEvents = 'none'; //prevent editing/sorting while popoutList is open
        },

        /**
         * Close the pop out options
         */
        onPopOutClosed () {
            this.isPopOutOpen = false; 
            document.querySelector('[data-list]').style.pointerEvents = 'auto';
        },

        /**
         * Push in a new row, retrieving the default value for each property and 
         * then adding in additional values required to track and add
         */
        onNewRowSelected () {
            const newRow = {}
            this.data.fields.forEach((field) => {
                if (Object.keys(field).includes('default')) {
                    newRow[`${field.id}`] = field.default;
                } else if (Object.keys(field).includes('lookup')) { //for lookups, use the first value as default
                    newRow[`${field.id}`] = field.lookup[0]?.key ? field.lookup[0].key : '';
                }
            });
            newRow.sequence = this.getNextSequence();
            newRow.id = this.getNextId(),
            newRow.isNew = true;
            this.rows = [...this.rows, newRow];
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
         */
        async onConfirmDeleteRowSelected () {
            this.isProcessing = true;
            this.$refs.toast.activate('Deleting row...');
            try {
                const response = await axios.post(this.data.deleteRoute, this.getDeleteRowFormData());
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
         * Send through the data to delete the row
         * 
         * @returns { FormData }
         */
        getDeleteRowFormData () {
            const formData = new FormData();
            formData.append('table', this.tableName);
            formData.append('id', this.selectedRow.id);
            return formData;
        },

        /**
         * Cancel the the selected row deletion
         */
        onCancelDeleteRowSelected () {
            this.$refs.deleteModal.close();
        },

        /**
         * Save all data
         */
        async onSaveSelected () {
            this.isSaving = true;
            this.isProcessing = true;
            this.saveStatus = 'Saving...';
            this.$refs.toast.activate('Saving...');
            this.updateSequenceOrder();
            try {
                const response = await axios.post(this.data.saveRoute, this.getSaveAllFormData());
                if (response.status === 200) {
                    this.updateNewRowIds(response.data.newRowIdList);
                    this.$refs.toast.timeout('Save successful', true, 2000);
                } else {
                    this.$refs.toast.timeout('Unable to save', false, 2000);
                }
            } catch (error) {
                this.$refs.toast.timeout('Unable to save', false, 2000);
            } finally {
                this.isSaving = false;
                this.isProcessing = false;
                this.saveStatus = 'Save';
            }
        },

        /**
         * Ensure the correct sorting order is reflected 
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
         * Send through the data to save the whole list
         * 
         * @returns { FormData }
         */
        getSaveAllFormData () {
            const formData = new FormData();
            formData.append('table', this.tableName);
            formData.append('rows', JSON.stringify(this.rows));
            return formData;
        },

        /**
         * The new rows have been assigned, so need to match the front end 
         * 
         * @param { Array or undefined }
         */
        updateNewRowIds (newRowIdList) {
            if (typeof newRowIdList !== 'undefined') { //won't be returned currently if there's no new rows
                const newRows = this.rows.filter((row) => row.id < 0);
                newRows.forEach((row, index) => {
                    row.id = newRowIdList[index];
                    row.isNew = false;
                });
            }
        },

        /**
         * animate settings to help context when filtering
         * refer to https://vuejs.org/v2/guide/transitions.html#Staggering-List-Transitions
         * what happens before shown
         *
         * @param {DOMElement} element - the element that's animating
         */
        onBeforeEnter (row) {
            if (!this.isSaving) {
                gsap.set(row, { height: 0 } );
            }
        },

        /**
         * how the element animates on
         *
         * @param {DOMElement} element - the element that's animating
         * @param {Function} done - to be called once animation is complete
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
         */
        onLeave (row, done) {
            if (this.isSaving) {
                done();
            } else {
                gsap.to(row, 0.3, { height: 0, onComplete: done } );
            }
        },

        /**
         * To highlight all rows which also have the same pop out option
         * for an easier visual way to check which rows have the same pop out option
         */
        onPopOutNameSelected (popOutName) {
            if (this.selectedNameInPopOut === popOutName) { //so can toggle on/off
                this.selectedNameInPopOut = '';
            } else {
                this.selectedNameInPopOut = popOutName;
            }

        }

    }

}
</script>

<template>

    <div>

        <div v-if="isDataLoaded">
        
            <div class="small-12 island">
                
                <transition name="fade" mode="out-in">

                    <div v-if="rows.length === 0" id="listEmptyState" class="empty-state" key="empty-list">
                        <i class="icon-list"></i>
                        <p>The {{ data.title }} list is empty.<br><a @click.prevent="onNewRowSelected" href="#add-an-item" data-button="add-row">Add an item to this list</a>.</p>
                    </div>

                    <form v-else method="post" action="/adminv2/lists/save" key="list">

                        <section>

                            <div class="list-container">

                                <admin-list-header 
                                    :header-fields="headerFields"
                                    :is-sortable="data.isSortable"
                                    :is-deletable="data.canDelete"
                                />

                                <transition-group
                                    name="staggered-fade"
                                    tag="ul"
                                    :class="`information-list ${ data.isSortable ? 'sortable ui-sortable' : 'unsortable'}`"
                                    :style="{ minHeight: popOutHeight + 'px' }"
                                    v-bind:css="false"
                                    data-sortable 
                                    data-list 
                                    @before-enter="onBeforeEnter"
                                    @enter="onEnter"
                                    @leave="onLeave"
                                >    
                                    <admin-list-row
                                        v-for="(row, index) in rowsSortedBySequence"
                                        :key="`${row.id}-${index}`"
                                        :header-fields="headerFields"
                                        :row="row"
                                        :selected-row="selectedRow"
                                        :is-sortable="data.isSortable"
                                        :can-delete="data.canDelete"
                                        :pop-out-header-field="popOutHeaderField"
                                        :pop-out-fields="popOutFields"
                                        :is-pop-out-open="isPopOutOpen"
                                        :selected-pop-out-name="selectedNameInPopOut"
                                        @on-pop-out-selected="onPopOutSelected"
                                        @on-pop-out-name-selected="onPopOutNameSelected"
                                        @on-delete-row-selected="onDeleteRow"
                                    />
                                </transition-group>

                                <admin-list-pop-out
                                    v-if="hasPopOut"
                                    :header-field="popOutHeaderField"
                                    :fields="popOutFields" 
                                    :selected-row="selectedRow"
                                    :is-open="isPopOutOpen" 
                                    :save-status="saveStatus" 
                                    :is-processing="isProcessing" 
                                    @on-pop-out-mounted="onPopOutMounted"
                                    @on-pop-out-saved="onSaveSelected"
                                    @on-pop-out-closed="onPopOutClosed"
                                />

                                <div v-if="!data.noNewRecords" class="component-action">
                                    <section>
                                        <a @click.prevent="onNewRowSelected" href="#add-new-row" data-button="add-row" class="button" :disabled="isProcessing">Add New Row</a>
                                    </section>
                                </div>

                            </div><!-- .list-container -->

                        </section>

                        <input type="submit" @click.prevent="onSaveSelected" :value="saveStatus" :disabled="isProcessing" />

                    </form>

                </transition>    

            </div><!-- small-12 island -->  

            <toast ref="toast"></toast>

            <reveal-modal v-if="data.canDelete" ref="deleteModal">
                <h2>Please Confirm</h2>
                <div data-alert class="alert-box warning">{{ data.deleteWarning }}</div>
                <ul class="flex-list buttons">
                    <li><a @click.prevent="onConfirmDeleteRowSelected" class="button submit">Yes</a></li>
                    <li><a @click.prevent="onCancelDeleteRowSelected" class="button">Cancel</a></li>
                </ul>
            </reveal-modal>

        </div><!-- v-if="isDataLoaded" -->

        <div v-else-if="hasDataLoadError">
            <h2>Unable to load the list data</h2>
        </div>

        <div v-else>
            <h2>Loading please wait</h2>
        </div>

    </div>

</template>

<style lang="scss" scoped>

.list-container { //so rows aren't shown when animating on/off on add/remove
    position: relative;
    overflow: hidden;
}

.information-list {
    position: relative;
}

.component-action {
    position: relative;
}

.fade-enter-active, .fade-leave-active {
  transition: opacity .3s;
}
.fade-enter, .fade-leave-to {
  opacity: 0;
}

</style>
