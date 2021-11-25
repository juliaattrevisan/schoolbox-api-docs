<script>

/**
 * Presents all the files within a homepage file component in a filterable table
 */

import { Event } from 'vue-tables-2';
import fileTypes from './viewAllFilesFileTypes.js';
import ViewAllFilesFilters from './view-all-files-filters.vue';
import ViewAllFilesRowName from './view-all-files-row-name.vue';
import ViewAllFilesRowEdit from './view-all-files-row-edit.vue';
import footerUI from '@vue/components/vueTables2/mixinFooterUI.js';
import BottomDrawer from '@vue/components/bottomDrawer/bottom-drawer.vue';
import Toast from '@vue/components/toast.vue';
import RevealModal from '@vue/components/reveal-modal.vue';
import { gsap } from 'gsap/all';


export default {

    components: {
        ViewAllFilesFilters,
        BottomDrawer,
        Toast,
        RevealModal,
    },

    mixins: [
        footerUI //to place and keep the tables UI in the footer
    ],

    props: {
        canEdit: { //whether the user has permission to modify files
            type: Boolean,
            required: true,
        },
        componentInstanceConfig: { //any config used within the homepage component instance, currently used to set initial sorting
            type: Object,
            required: true,
        },
        files: { //all files within the component
            type: Array,
            required: true,
        },
        presetFilters: { //the list of currently selected filters passed through
            type: Array,
            required: true,
        },
        sourceFolderId: { //to know where to upload files to
            type: Number,
            required: true,
        },
    },

    data () {
        return {
            selectedFilters: [],
            tableData: [],
            tableOptions: { //options for vue-tables-2: https://matanya.gitbook.io/vue-tables-2/options-api
                cellClasses: {
                    name: [ {
                        class: 'new-file-upload',
                        condition: (row) => row.isNew === true
                    } ],
                },
                columnsClasses: {
                    ['dateModified.formatted']: 'date-modified-td ', //to hide the column for mobile
                    edit: 'edit-td ', //to specify a width
                },
                customFilters: [ //refer to https://matanya.gitbook.io/vue-tables-2/custom-filters for further info
                    {
                        name: fileTypes.DOCUMENT,
                        callback: (row, query) => row.type !== query
                    },
                    {
                        name: fileTypes.AUDIO,
                        callback: (row, query) => row.type !== query
                    },
                    {
                        name: fileTypes.VIDEO,
                        callback: (row, query) => row.type !== query
                    },
                    {
                        name: fileTypes.IMAGE,
                        callback: (row, query) => row.type !== query
                    },
                    {
                        name: fileTypes.OTHER,
                        callback: (row, query) => row.type !== query
                    },
                ],
                customSorting: {
                    name: (ascending) => { //sort name naturally
                        return (a, b) => {
                            if (ascending) {
                                return this.collator.compare(a.name, b.name);
                            } else {
                                return this.collator.compare(b.name, a.name);
                            }
                        }
                    },
                    ['dateModified.formatted']: (ascending) => { //sort date modified accurately by the timestamp
                        return (a, b) => {
                            if (ascending) {
                                return a.dateModified.timestamp >= b.dateModified.timestamp ? 1 : -1;
                            }
                            return a.dateModified.timestamp <= b.dateModified.timestamp ? 1 : -1;
                        }
                    },
                },
                debounce: 100, //shorter debounce time as data is stored locally
                headings: {
                    name: 'Name',
                    ['dateModified.formatted']: 'Date modified',
                    edit: '',
                },
                orderBy: { //use the passed through component instance config to match the initial sorting
                    ascending: this.componentInstanceConfig.sortdir.value === 'asc' ? true : false,
                    column: this.componentInstanceConfig.sort.value === 'name' ? 'name' : 'dateModified.formatted',
                },
                perPage: 100,
                perPageValues: [100, 200, 300],
                resizableColumns: false,
                selectable: {
                    mode: 'multiple' //to enable multi-select
                },
                sortable: ['name', 'dateModified.formatted'], //the edit column doesn't need to be sortable
                sortIcon: {
                    is: 'glyphicon-sort',
                    base: 'glyphicon',
                    up: 'glyphicon-chevron-up',
                    down: 'glyphicon-chevron-down'
                },
                tabbable: false,
                templates: { //custom vue templates - refer to https://matanya.gitbook.io/vue-tables-2/templates for further info
                    name: ViewAllFilesRowName,
                    edit: ViewAllFilesRowEdit,
                },
                texts: {
                    count: '{from}-{to} of {count} files|{count} files|1 file',
                    filterPlaceholder: 'Search files',
                    noResults: 'No matching files',
                },
            },
            tableColumns: this.getTableColumns(),
            collator: new Intl.Collator('en', { numeric: true, sensitivity: 'base' }), //to natually sort name column
            selectedRows: [], //keep track of which rows could be deleted/downloaded
            singleRowName: 'file',
            pluralRowName: 'files',
            isShiftKeySelected: false, //to multi-select fields
            hasSelectedShiftKey: false, //to hide a UI note
        }
    },

    computed: {

        /**
         * To track how many are selected
         *
         * @returns { Number }
         */
        selectedRowsCount () {
            return this.selectedRows.length;
        },

        /**
         * For proper count display, eg: '1 user' / '4 users'
         *
         * @returns { String }
         */
        selectedRowsCountWithRowName () {
            if (this.selectedRowsCount === 0) {
                return 'No ' + this.singleRowName;
            } else if (this.selectedRowsCount === 1) {
                return this.selectedRowsCount + ' ' + this.singleRowName;
            } else {
                return this.selectedRowsCount + ' ' + this.pluralRowName;
            }
        },

    },

    created () {
        this.selectedFilters = [...this.presetFilters];
    },

    mounted () {
        this.initTable();
        this.onTableLoaded();
        this.initFilterDisplay();
    },

    methods: {

        /**
         * Populate the table with the files list
         */
        initTable () {
            this.tableData = this.files.map((file) => file = { ...file, isNew: false } ); //add isNew so can differentiate newly uploaded files
        },

        /**
         * The edit column is only display to those that have permission
         *
         * @returns { Array }
         */
        getTableColumns () {
            const tableColumns = ['name', 'dateModified.formatted'];
            if (this.canEdit) {
                tableColumns.push('edit');
            }
            return tableColumns;
        },

        /**
         * To update the table to show the UI in the footer
         */
        onTableLoaded () {
            this.$nextTick(() => {
                this.footerUI_update();
                this.initDropZone();
            });
        },

        /**
         * To Oonly show those files within the currently filtered list
         */
        initFilterDisplay () {
            if (!this.selectedFilters.includes(fileTypes.DOCUMENT)) {
                Event.$emit(`vue-tables.filter::${fileTypes.DOCUMENT}`, fileTypes.DOCUMENT);
            }
            if (!this.selectedFilters.includes(fileTypes.AUDIO)) {
                Event.$emit(`vue-tables.filter::${fileTypes.AUDIO}`, fileTypes.AUDIO);
            }
            if (!this.selectedFilters.includes(fileTypes.VIDEO)) {
                Event.$emit(`vue-tables.filter::${fileTypes.VIDEO}`, fileTypes.VIDEO);
            }
            if (!this.selectedFilters.includes(fileTypes.IMAGE)) {
                Event.$emit(`vue-tables.filter::${fileTypes.IMAGE}`, fileTypes.IMAGE);
            }
            if (!this.selectedFilters.includes(fileTypes.OTHER)) {
                Event.$emit(`vue-tables.filter::${fileTypes.OTHER}`, fileTypes.OTHER);
            }
        },

        /**
         * Add/remove from selectedFilters and fire an event for the table
         *
         * @param { String }
         */
        onFilterChange (fileType) {
            if (this.selectedFilters.includes(fileType)) {
                this.selectedFilters = this.selectedFilters.filter((filterType) => filterType !== fileType);
            } else {
                this.selectedFilters = [...this.selectedFilters, fileType];
            }
            const filterParam = this.selectedFilters.includes(fileType) ? '' : fileType ;
            Event.$emit(`vue-tables.filter::${fileType}`, filterParam);
        },

        /**
         * To be able to drag and drop files straight onto the table
         */
        initDropZone () {
            $('#dropzone').fileDrop({
                previewsContainer: '#view-all-files [data-dz-container]',
                previewTemplate: '#view-all-files [data-dz-template]',
                reloadContent: false,
                sending: (file, xhr, formData) => {
                    this.appendFormData(file, formData);
                },
                success: (file, response) => {
                    this.displayFileUploadSuccess(file);
                    this.addFileToTable(response);
                },
                error: (file, response) => {
                    this.displayFileUploadError(file, response);
                }
            });
        },

        /**
         * Add the extra properties required for the /fileForm.php endpoint
         *
         * @param { Object }
         * @param { FormData }
         */
        appendFormData (file, formData) {
            const fileDescription = file.name.substr(0, file.name.lastIndexOf('.'));
            const uploadDescription = fileDescription.length > 0 ? fileDescription : file.name; //ensure there's a file name, otherwise just add the extension
            formData.append('description', uploadDescription);
            formData.append('category', this.sourceFolderId);
            formData.append('comment', '');
            formData.append('hidden', 0);
        },

        /**
         * Update the display to notify of success and animate it out
         *
         * @param { Object }
         */
        displayFileUploadSuccess (file) {
            const previewElement = file.previewElement;
            previewElement.classList.remove('dz-processing');
            previewElement.querySelector('[data-dz-thumbnail]').setAttribute('src', `/ajax/mime/${file.type}`);
            previewElement.querySelector('span.dz-upload').classList.add('complete');
            previewElement.querySelector('[data-dz-status]').textContent = 'Success';
            previewElement.style.overflow = 'hidden';
            gsap.to(previewElement, {
                height: 0,
                delay: 1,
                duration: 0.5,
                onComplete: () => previewElement.remove()
            } );
        },

        /**
         * Add the new file into the table
         *
         * @param { Object }
         */
        addFileToTable (file) {
            if (!this.selectedFilters.includes(file.type)) {
                this.selectedFilters = [...this.selectedFilters, file.type];
                Event.$emit(`vue-tables.filter::${file.type}`, '');
            }
            const fileWithIsNew = { ...file, isNew: true };
            this.tableData = [...this.tableData, fileWithIsNew];
        },

        /**
         * Can't upload the file, so notify the reason and show the close button
         *
         * @param { Object }
         * @param { Object }
         */
        displayFileUploadError (file, response) {
            document.querySelector('.dz-error [data-close]').classList.remove('hide');
            file.previewElement.classList.remove('dz-processing');
            file.previewElement.querySelector('[data-dz-status]').textContent = response.error;
        },







        /**
         * to keep track of selected rows
         */
        onRowSelected (event) {
            this.selectedRows = event;
            if (this.selectedRows.length > 1 && this.isShiftKeySelected) { //to hide a UI note, notifying of availability of shift-select
                this.hasSelectedShiftKey = true;
            }
        },

        /**
         * To confirm/cancel selected users deletion
         */
        onDeleteFilesSelected () {
            this.$refs.deleteModal.open();
        },

        /**
         * Delete the selected users, informing of deletion and clearing them from the table list as well
         *
         */
        async onConfirmDeleteFilesSelected () {
            // this.$refs.toast.activate('Deleting...');
            // try {
            //     await axios.post(this.action, this.getFormData());
            //     this.$refs.toast.timeout('Deleted', true, 2000);
            //     this.$refs.deleteModal.close();
            //     this.rows = this.getUpdatedRowsAfterUserDeletion();
            //     this.clearRows();
            //     this.$refs.table.resetSelectedRows();
            // } catch (error) {
            //     this.$refs.toast.timeout('Unable to delete', false, 2000);
            // }    
        },

        /**
         * Set up a deleted users array to POST
         *
         * @returns {FormData}
         */
        // getFormData () {
        //     const usersToDeleteIDList = this.selectedRows.map(row => row.id);
        //     const formData = new FormData();
        //     usersToDeleteIDList.forEach((item) => {
        //         formData.append('users[]', item);
        //     });
        //     return formData;
        // },

        /**
         * To update the row display after deletion
         *
         * @returns {Array}
         */
        // getUpdatedRowsAfterUserDeletion () {
        //     return this.rows.filter((row) => {
        //         if (this.selectedRows.some(rowToDelete => rowToDelete.id === row.id)) { //pull out any deleted rows, those rows that are in selectedRows, which have just been deleted
        //             return false;
        //         }
        //         return true;
        //     });
        // },

        /**
         * clear the selection after deletion
         */
        clearRows () {
            this.selectedRows = [];
        },

        /**
         * Cancel the the selected users deletion
         */
        onCancelDeleteUsersSelected () {
            this.$refs.deleteModal.close();
        },

        /**
         * To confirm/cancel selected users deletion
         */
        onClearFilesSelected () {
            this.clearRows();
            this.$refs.table.resetSelectedRows();
        },



    },

}

</script>

<template>

    <div class="row" id="view-all-files">

        <div class="small-12 columns">

            <div class="dropzone-wrap">

                <form class="dropzone overlay" id="dropzone" action="/cms/fileForm.php?ajax=1" method="post">
                    <div class="dz-default dz-message">You can drag files into this space.</div>
                </form>

                <v-client-table
                    ref="table"
                    :data="tableData"
                    :columns="tableColumns"
                    :options="tableOptions"
                    @select="onRowSelected"
                >
                    <div slot="beforeTable">
                        <view-all-files-filters
                            :selected-filters="selectedFilters"
                            @on-filter-change="onFilterChange"
                        />
                    </div>
                    <div slot="afterTable">
                        <div class="dropped-files-container">
                            <div data-dz-container=""></div>
                            <div data-dz-template="" class="hide">
                                <div data-dz-template-item>
                                    <div class="card small-12">
                                        <button class="close-button hide" aria-label="Close alert" type="button" data-close="">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        <img data-dz-thumbnail="" class="mime" src="/static/images/foundation/preloader.gif" alt="file loading spinner">
                                        <p data-dz-name=""></p>
                                        <p class="meta" data-dz-status="">Uploading...</p>
                                        <div class="progress dz-progress">
                                            <span class="meter dz-upload" style="width:0;"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="VueTables__table-footer" ref="tableFooter"></div>
                        <div class="component-action">
                            <section>
                                <p><a :href="`/cms/file/new/${this.sourceFolderId}`">Select a file</a> or drag and drop multiple onto this component</p>
                            </section>
                        </div>
                    </div>
                </v-client-table>

            </div>

            <toast ref="toast"></toast>

            <reveal-modal ref="deleteModal">
                <h2>Please Confirm</h2>
                <p>Are you sure you wish to delete the selected files?</p>
                <ul class="flex-list buttons">
                    <li><button @click.prevent="onConfirmDeleteFilesSelected" class="button submit">Delete</button></li>
                    <li><button @click.prevent="onCancelDeleteFilesSelected" class="button">Cancel</button></li>
                </ul>
            </reveal-modal>

            <bottom-drawer :showDrawer="selectedRowsCount > 0">
                <h3 class="selected-count"><em>{{ selectedRowsCountWithRowName }} selected</em></h3>
                <span v-if="!hasSelectedShiftKey" class="shift-select-note">Use Shift+Select to select multiple files</span>
                <button @click.prevent="onClearFilesSelected" class="button-clear">Clear file selection</button>
                <ul class="flex-list buttons">
                    <li><button @click.prevent="onDeleteFilesSelected" class="button submit icon-delete">Delete {{ selectedRowsCountWithRowName }}</button></li>
                    <li><button @click.prevent="onDownloadFilesSelected" class="button submit icon-download">Download {{ selectedRowsCountWithRowName }}</button></li>
                </ul>
            </bottom-drawer>

        </div>

    </div>

</template>

<style lang="scss">

@import '@vue/components/vueTables2/vueTables2';

.VueTables table {
    table-layout: fixed; //to ensure the flex within view-all-files-row-name can shrink all the way down
}

.VueTables__search {
    padding-bottom: 0.5rem; //to put the view-all-files-filters.vue up closer to the search input
}

.VueTables__search-field label {
    display: none; //there's a 'Search files' in the input and 'Filter to' label below in view-all-files-filters.vue, so not needed
}

.VueTables__search-field input {
    margin-bottom: 0; //to put the view-all-files-filters.vue up closer to the search input
}

.VueTables__table td, .VueTables__select-all {
    padding: 0.25rem 0.5rem 0.25rem 0.5rem;
    vertical-align: middle;
    @media (max-width: 700px) {
        padding: 0.5rem 0.5rem 0 0;
    }
}

.VueTables__select-row {
    width: 30px;
}

.date-modified-td {
    width: 15.625rem; //keep on the right hand side, and don't alter width when filtering changes
    @media (max-width: 1000px) {
        width: 9.375rem;
    }
    @media (max-width: 700px) {
        display: none; //hide on mobile, as the date will then be shown within view-all-files-name.vue
    }
}

.edit-td {
    width: 2.5rem; //keep the edit icon on the right side
}

.dropped-files-container {
    background: white;
    ul li {
        list-style-type: none;
    }
    .card {
        background: white;
    }
}

.VueTables__table-footer {
    margin-bottom: 0; //.component action sits underneath
}

.component-action {
    margin-bottom: 2rem; //add margin as it's below the table-footer
}

.new-file-upload {
    animation-duration: 1s;
    animation-name: fadeIn;
}

@keyframes fadeIn {
    0% { opacity: 0; }
    100% { opacity: 1; }
}

.close-button { //move to be more inline with the edit icons
    right: 1.25rem;
    top: 0.25rem;
}

.selected-count {
    margin-right: 1rem;
}

.button-clear, .button-delete, .selected-count {
    display: inline-block;
}

.shift-select-note {
    color: #9a9a9a;
    margin-right: 1rem;
    font-size: 13px;
    font-style: italic;
}

.button-clear {
    padding: 0px;
    background: none;
    vertical-align: baseline;
    font-size: 0.8rem;
    &:hover {
        background: none;
    }
}

</style>
