<script>

/**
 * Delete a row, either straight away when the row hasn't bee added to the DB,
 * or via a confirmation modal when the row is already in the DB
 */

import { mapState, mapActions } from 'pinia';
import { useAdminListStore } from './store/adminListStore';
import RevealModal from '@schoolboxVue/components/reveal-modal';
import Toast from '@schoolboxVue/components/toast';

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

        ...mapState(useAdminListStore, ['loadedData', 'rows', 'rowToDelete']),

    },

    watch: {

        rowToDelete (newValue) {
            if (newValue && newValue.isNew) { //hasn't been placed in the DB yet, so no need to confirm its removal
                const rowToRemove = this.rows.find((row) => row.id === newValue.id);
                this.deleteRowConfirmed(rowToRemove);
            } else if (newValue) { //otherwise open up the modal to confirm or cancel
                this.$refs.deleteModal.open();
            }
        }

    },

    methods: {

        ...mapActions(useAdminListStore, ['deleteRowConfirmed', 'deleteRowCancelled']),

        /**
         * Notify the DB and pull out the selectedRow
         * on delete confirmation
         */
        async onConfirmDeleteRowSelected () {
            try {
                this.$refs.toast.activate('Deleting row...');
                const response = await fetch(this.loadedData.deleteRoute, {
                    method: 'POST',
                    body: this.getDeleteRowFormData(),
                });
                const responseData = await response.json();
                if (responseData.result !== false) {
                    const deletedRow = this.rows.find((row) => row.id === this.rowToDelete.id);
                    this.deleteRowConfirmed(deletedRow);
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
         * Cancel the selected row deletion
         */
        onCancelDeleteRowSelected () {
            this.$refs.deleteModal.close();
        },

        /**
         * Clear the currently selected rowToDelete
         */
        onDeleteRowModalClosed () {
            this.deleteRowCancelled();
        },

    }

}

</script>

<template>
    <div>
        <reveal-modal ref="deleteModal" @closed="onDeleteRowModalClosed">
            <h2>Please Confirm</h2>
            <div data-alert class="alert-box warning">{{ loadedData.deleteWarning ? loadedData.deleteWarning : 'Are you sure you wish to delete this row?' }}</div>
            <ul class="flex-list buttons">
                <li><a @click.prevent="onConfirmDeleteRowSelected" @keyup.enter="onConfirmDeleteRowSelected" class="button submit">Yes</a></li>
                <li><a @click.prevent="onCancelDeleteRowSelected" @keyup.enter="onCancelDeleteRowSelected" class="button">Cancel</a></li>
            </ul>
        </reveal-modal>
        <toast ref="toast"></toast>
    </div>
</template>
