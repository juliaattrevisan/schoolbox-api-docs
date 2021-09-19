/**
 * To save the rows data to the db, used by both admin-list.vue and admin-list-pop-out.vue
 */

import { mapState } from 'vuex';
import axios from 'axios';
import { UPDATE_SEQUENCE } from '../store/adminListMutationTypes';
 
export default {
    
    data () {
        return {
            saveRows_isProcessing: false,
            saveRows_saveStatus: 'Save'
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
        async saveRows_onSaveSelected () {
            try {
                this.saveRows_isProcessing = true;
                this.saveRows_saveStatus = 'Saving...';
                this.$refs.toast.activate(this.saveRows_saveStatus);
                this.updateSequenceOrder();
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
                    this.saveRows_isProcessing = false;
                    this.saveRows_saveStatus = 'Save';
                });
            }
        },

        /**
         * Ensure the correct sorting order is reflected 
         */
        updateSequenceOrder () {
            const newSequenceList = [];
            const allRowElements = document.querySelector('[data-list]').children;
            Array.from(allRowElements).forEach((rowElement) => { //loop through each row in order to set the latest correct sequence
                newSequenceList.push(rowElement.dataset.rowId);
            });
            this.$store.commit(UPDATE_SEQUENCE, newSequenceList);
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
  