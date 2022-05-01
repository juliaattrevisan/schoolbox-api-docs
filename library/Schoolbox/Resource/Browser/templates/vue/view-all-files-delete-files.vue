<script>

/**
 * Provides UI for deleting selected rows and processing a confirmed request
 */

import BottomDrawer from '@schoolboxVue/components/bottomDrawer/bottom-drawer.vue';
import Toast from '@schoolboxVue/components/toast.vue';
import RevealModal from '@schoolboxVue/components/reveal-modal.vue';


export default {

    components: {
        BottomDrawer,
        Toast,
        RevealModal,
    },

    props: {
        selectedFiles: {
            type: Array,
            required: true,
        },
        isShiftKeySelected: {
            type: Boolean,
            required: true,
        },
        hasSelectedShiftKey: {
            type: Boolean,
            required: true,
        },
    },

    data () {
        return {
            singleRowName: 'file',
            pluralRowName: 'files',
            
        }
    },

    computed: {

        /**
         * To track how many are selected 
         *
         * @returns { Number }
         */
        selectedFilesCount () {
            return this.selectedFiles.length;
        },

        /**
         * For proper count display, eg: '1 file' / '4 file'
         *
         * @returns { String }
         */
        selectedFilesCountWithRowName () {
            if (this.selectedFilesCount === 0) {
                return 'No ' + this.singleRowName;	
            } else if (this.selectedFilesCount === 1) {
                return this.selectedFilesCount + ' ' + this.singleRowName;	
            } else {
                return this.selectedFilesCount + ' ' + this.pluralRowName;	
            }
        },

    },
    
    methods: {

        /**
         * To confirm/cancel selected files deletion
         */
        onDeleteFilesSelected () {
            this.$refs.deleteModal.open();
        },

        /**
         * Delete the selected files, informing of deletion and clearing them from the table list as well
         */
        async onConfirmDeleteFilesSelected () {
            this.$refs.toast.activate('Deleting...');
            try {
                const fileDeletions = await Promise.all(this.getFetches());
                const fileStatuses = fileDeletions.map((file) => file.status); //grab the returned status of each file deletion response
                const hasDeleteErrors = fileStatuses.some((status) => status !== 200); //check if all files were able to be deleted
                if (hasDeleteErrors) { //and notify of error if it appears not all deletons were successful
                    this.$refs.toast.timeout('Sorry, there was an error deleting all selected files. Please refresh the page to ensure your file list is up to date.', false, 5000);
                } else { //otherwise appears everything is aok
                    this.$refs.toast.timeout('Deleted', true, 2000);
                    this.$refs.deleteModal.close();
                    this.$emit('on-files-deleted'); //emit to view-all-files.vue
                }
            } catch (error) {
                this.$refs.toast.timeout('Sorry, there was an error deleting all selected files. Please refresh the page and try again.', false, 3000);
            }    
        },

        /**
         * To delete all the marked files in parallel
         * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/all
         *
         * @returns { Array }
         */
        getFetches () {
            const fetchList = [];
            this.selectedFiles.forEach((row) => {
                fetchList.push(fetch(`/cms/fileForm.php?${this.getURLSearchParams(row)}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                }));
            });
            return fetchList;
        },

        /**
         * Set up a deleted files array to POST
         *
         * @returns { URLSearchParams }
         */
        getURLSearchParams (row) {
            return new URLSearchParams({
                ajax: '1',
                delete: row.id,
            });
        },

        /**
         * Cancel the the selected files deletion
         */
        onCancelDeleteFilesSelected () {
            this.$refs.deleteModal.close();
        },

        /**
         * To confirm/cancel selected files deletion
         */
        onClearFileSelectionSelected () {
            this.$emit('on-selection-cleared'); //emit to view-all-files.vue
        },

    }

}

</script>

<template>
   <div>
        <toast ref="toast"></toast>
        <reveal-modal ref="deleteModal">
            <h2>Please Confirm</h2>
            <p>Are you sure you wish to delete the selected files?</p>
            <ul class="flex-list buttons">
                <li><button @click.prevent="onConfirmDeleteFilesSelected" class="button submit">Delete</button></li>
                <li><button @click.prevent="onCancelDeleteFilesSelected" class="button">Cancel</button></li>
            </ul>
        </reveal-modal>
        <bottom-drawer :showDrawer="selectedFilesCount > 0">
            <h3 class="selected-count"><em>{{ selectedFilesCountWithRowName }} selected</em></h3>
            <span v-if="!hasSelectedShiftKey" class="shift-select-note">Use Shift+Select to select multiple files</span>
            <button @click.prevent="onClearFileSelectionSelected" class="button-clear">Clear file selection</button>
            <button @click.prevent="onDeleteFilesSelected" class="button submit icon-delete">Delete {{ selectedFilesCountWithRowName }}</button>
        </bottom-drawer>
   </div>
</template>

<style lang="scss">

.selected-count {
    margin-right: 1rem;
}

.button-clear, .button-delete, .selected-count {
    display: inline-block;
}

.shift-select-note {
    color: #9a9a9a;
    font-size: 13px;
    font-style: italic;
    margin-right: 1rem;
}

.button-clear {
    background: none;
    font-size: 0.8rem;
    padding: 0px;
    vertical-align: baseline;
    &:hover {
        background: none;
    }
}

</style>