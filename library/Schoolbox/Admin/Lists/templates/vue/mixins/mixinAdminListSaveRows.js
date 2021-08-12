/**
 * Helper for Vue.js FullCalendar implementations:
 * contains common code that can be shared between all implementations.
 */

import { mapState } from 'vuex';
import axios from 'axios';
 
export default {
    
    data () {
        return {
            mixinSaveRows_isProcessing: false,
            mixinSaveRows_saveStatus: 'Save'
        }
    },

    computed: {
        ...mapState({
            loadedData: state => state.loadedData,
            rows: state => state.rows
        }),
    },
  
    methods: {

        /**
         * Save all data
         */
        async mixinSaveRows_onSaveSelected () {
            try {
                this.mixinSaveRows_isProcessing = true;
                this.mixinSaveRows_saveStatus = 'Saving...';
                // this.$refs.toast.activate(this.saveStatus);
                this.updateSequenceOrder();
                const response = await axios.post(this.loadedData.saveRoute, this.getSaveAllFormData());
                if (response.status === 200) {
                    this.updateNewRowIds(response.data.newRowIdList);
                    // this.$refs.toast.timeout('Save successful', true, 2000);
                } else {
                    // this.$refs.toast.timeout('Unable to save', false, 2000);
                }
            } catch (error) {
                console.warn(error);
                // this.$refs.toast.timeout('Unable to save', false, 2000);
            } finally {
                this.$nextTick(() => { //to avoid animating any newly inserted rows in onBeforeEnter 
                    this.mixinSaveRows_isProcessing = false;
                    this.mixinSaveRows_saveStatus = 'Save';
                });
            }
        },

        /**
         * Ensure the correct sorting order is reflected 
         */
        updateSequenceOrder () {
            const allRowElements = document.querySelector('[data-list]').children;
            Array.from(allRowElements).forEach((rowElement, index) => { //loop through each row in order to set the correct sequence
                const rowId = rowElement.dataset.rowId; //how else do we get the row id? 
                const matchingRowData = this.rows.find((row) => Number(row.id) === Number(rowId)); //get the row data matched to the current row DOM element
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

    }
  
  }
  