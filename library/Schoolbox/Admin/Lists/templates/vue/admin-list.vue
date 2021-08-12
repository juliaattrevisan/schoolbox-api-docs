<script>

/**
 * Provide access to CRUD Admin > Lists
 *
 */

import { mapState, mapGetters } from 'vuex';
import axios from 'axios';
import { gsap } from 'gsap/all'
import AdminListHeader from './admin-list-header';
import AdminListPopOut from './admin-list-pop-out';
import AdminListNoRows from './admin-list-no-rows';
import AdminListRow from './admin-list-row';
import AdminListAddRow from './admin-list-add-row';
import Toast from '@schoolbox/toast';
import RevealModal from '@schoolbox/reveal-modal';


export default {

    components: {
        AdminListHeader,
        AdminListPopOut,
        AdminListNoRows,
        AdminListRow,
        AdminListAddRow,
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
            popOutHeight: 0, //so the popout can allow enough vertical space to fit the pop out
        }
    },

    computed: {

        ...mapState({
            loadedData: state => state.loadedData,
            rows: state => state.rows,
            selectedRow: state => state.selectedRow,
            isPopOutOpen: state => state.isPopOutOpen,
            saveStatus: state => state.saveStatus,
            isProcessing: state => state.isProcessing,
        }),

        ...mapGetters([
            'headerFields'
        ]), 

        /**
         * Whether the admin-list-pop-out.vue is requred
         * 
         * @returns { Boolean }
         */
        hasPopOut () {
            return this.loadedData.fields.some((field) => field.hasOwnProperty('isPopOutList')); 
        }, 

        /**
         * To display the rows based on how they have been sorted
         *
         * @returns { Array of Objects }
         */
        rowsSortedBySequence () {
            return this.rows.slice().sort((a, b) => a.sequence - b.sequence);
        },

        /**
         * Retrieve any styles of the list that change with state
         *
         * @returns { Object }
         */
        listStyles () {
            return {
                minHeight: this.popOutHeight + 'px', //so the popout can allow enough vertical space to fit the pop out
                pointerEvents: this.isPopOutOpen ? 'none' : 'auto' //prevent editing/sorting while popoutList is open
            }
        }

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
                this.$store.commit('setLoadedData', response.data);
            } catch (error) {
                console.warn(error);
                this.hasDataLoadError = true; //show load error
            } finally {
                this.isDataLoaded = true; //update the display to show the list
                this.initSortable();
            }
        },

        /**
         * Init the sortable feature
         */
        initSortable () {
            if (this.loadedData.isSortable) {
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
         * Open the delete modal, storing the selected row index for possible deletion
         *
         * @param { Number }
         */
        onDeleteRowSelected (selectedRow) {
            this.$store.commit('setSelectedRow', selectedRow);
            if (selectedRow.isNew) { //hasn't been placed in the DB yet, so no need to confirm its removal
                const rowToRemove = this.rows.find((row) => row.id === this.selectedRow.id);
                this.$store.commit('deleteRow', rowToRemove);
            } else {
                this.$refs.deleteModal.open();
            }
        },

        /**
         * Notify the DB and pull out the selectedRow
         * on delete confirmation
         */
        async onConfirmDeleteRowSelected () {
            try {
                this.$store.commit('setIsProcessing', true);
                this.$refs.toast.activate('Deleting row...');
                const response = await axios.post(this.loadedData.deleteRoute, this.getDeleteRowFormData());
                if (response.result !== false) {
                    const deletedRow = this.rows.find((row) => row.id === this.selectedRow.id);
                    this.$store.commit('deleteRow', deletedRow);
                    this.$refs.toast.timeout('Row Deleted', true, 2000);
                } else {
                    this.$refs.toast.timeout('Unable to delete', false, 2000);
                }
            } catch (error) {
                console.warn(error);
                this.$refs.toast.timeout('Unable to delete', false, 2000);
            } finally {
                this.$refs.deleteModal.close();
                this.$store.commit('setIsProcessing', false);
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
            this.$store.commit('setIsProcessing', true);
            this.$store.commit('setSaveStatus', 'Saving...');
            this.$refs.toast.activate(this.saveStatus);
            this.updateSequenceOrder();
            try {
                const response = await axios.post(this.loadedData.saveRoute, this.getSaveAllFormData());
                if (response.status === 200) {
                    this.updateNewRowIds(response.data.newRowIdList);
                    this.$refs.toast.timeout('Save successful', true, 2000);
                } else {
                    this.$refs.toast.timeout('Unable to save', false, 2000);
                }
            } catch (error) {
                console.warn(error);
                this.$refs.toast.timeout('Unable to save', false, 2000);
            } finally {
                this.$nextTick(() => { //to avoid animating any newly inserted rows in onBeforeEnter 
                    this.$store.commit('setIsProcessing', false);
                    this.$store.commit('setSaveStatus', 'Save');
                });
            }
        },

        /**
         * Ensure the correct sorting order is reflected 
         */
        updateSequenceOrder () {
            const allRowElements = document.querySelector('[data-list]').children;
            Array.from(allRowElements).forEach((rowElement, index) => {
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
            if (!this.isProcessing) {
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
            if (this.isProcessing) {
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
            if (this.isProcessing) {
                done();
            } else {
                gsap.to(row, 0.3, { height: 0, onComplete: done } );
            }
        },

    }

}
</script>

<template>

    <div>

        <div v-if="isDataLoaded">
        
            <div class="small-12 island">
                
                <transition name="fade" mode="out-in">
                    
                    <admin-list-no-rows v-if="rows.length === 0" key="empty-list" />

                    <form v-else method="post" action="/adminv2/lists/save" key="list">

                        <section>

                            <div class="list-container">

                                <admin-list-header />

                                <transition-group
                                    name="staggered-fade"
                                    tag="ul"
                                    :class="`information-list ${ loadedData.isSortable ? 'sortable ui-sortable' : 'unsortable'}`"
                                    :style="listStyles"
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
                                        @on-delete-row-selected="onDeleteRowSelected"
                                    />
                                </transition-group>

                                <admin-list-pop-out
                                    v-if="hasPopOut"
                                    @on-pop-out-mounted="onPopOutMounted"
                                    @on-pop-out-saved="onSaveSelected"
                                />

                                <admin-list-add-row v-if="!loadedData.noNewRecords" />

                            </div><!-- .list-container -->

                        </section>

                        <input type="submit" @click.prevent="onSaveSelected" :value="saveStatus" :disabled="isProcessing" />

                    </form>

                </transition>    

            </div><!-- small-12 island -->  

            <toast ref="toast"></toast>

            <reveal-modal v-if="loadedData.canDelete" ref="deleteModal">
                <h2>Please Confirm</h2>
                <div data-alert class="alert-box warning">{{ loadedData.deleteWarning }}</div>
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
