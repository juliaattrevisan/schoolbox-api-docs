<script>

/**
 * Delete a row, either straight away when the row hasn't bee added to the DB,
 * or via a confirmation modal when the row is already in the DB
 */

import { mapState } from 'vuex';
import axios from 'axios';
import RevealModal from '@schoolbox/reveal-modal';
import Toast from '@schoolbox/toast';
import { DELETE_ROW_CONFIRMED } from './store/adminListMutationTypes';

export default {
    
    components: {
        RevealModal,
        Toast
    },

    props: {
        tableName: { //the name of the current list, used to submit updates to the DB
            type: String,
            required: true
        }
    },

    computed: {

        ...mapState({
            loadedData: state => state.loadedData,
            rows: state => state.rows,
            rowToDelete: state => state.rowToDelete,
        }),

    },

    watch: {

        rowToDelete (newValue) {
            if (newValue.isNew) { //hasn't been placed in the DB yet, so no need to confirm its removal
                const rowToRemove = this.rows.find((row) => row.id === newValue.id);
                this.$store.commit(DELETE_ROW_CONFIRMED, rowToRemove);
            } else { //otherwise open up the modal to confirm or cancel
                this.$refs.deleteModal.open();
            }
        }

    },

    methods: {

        /**
         * Notify the DB and pull out the selectedRow
         * on delete confirmation
         */
        async onConfirmDeleteRowSelected () {
            try {
                this.$refs.toast.activate('Deleting row...');
                const response = await axios.post(this.loadedData.deleteRoute, this.getDeleteRowFormData());
                if (response.result !== false) {
                    const deletedRow = this.rows.find((row) => row.id === this.rowToDelete.id);
                    this.$store.commit(DELETE_ROW_CONFIRMED, deletedRow);
                    this.$refs.toast.timeout('Row Deleted', true, 2000);
                } else {
                    this.$refs.toast.timeout('Unable to delete', false, 2000);
                }
            } catch (error) {
                console.warn(error);
                this.$refs.toast.timeout('Unable to delete', false, 2000);
            } finally {
                this.$refs.deleteModal.close();
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
            formData.append('id', this.rowToDelete.id);
            return formData;
        },

        /**
         * Cancel the the selected row deletion
         */
        onCancelDeleteRowSelected () {
            this.$refs.deleteModal.close();
        },

    }

}

</script>

<template>
    <div>
        <reveal-modal ref="deleteModal">
            <h2>Please Confirm</h2>
            <div data-alert class="alert-box warning">{{ loadedData.deleteWarning ? loadedData.deleteWarning : 'Are you sure you wish to delete this row?' }}</div>
            <ul class="flex-list buttons">
                <li><a @click.prevent="onConfirmDeleteRowSelected" class="button submit">Yes</a></li>
                <li><a @click.prevent="onCancelDeleteRowSelected" class="button">Cancel</a></li>
            </ul>
        </reveal-modal>
        <toast ref="toast"></toast>
    </div>
</template>
