<script>

/**
 * Presents all the files within a homepage file component in a filterable table
 */

import { EventBus } from 'v-tables-3-select-rows';
import fileTypes from './viewAllFilesFileTypes.js';
import ViewAllFilesFilters from './view-all-files-filters.vue';
import ViewAllFilesDropzone from './view-all-files-dropzone.vue';
import ViewAllFilesDeleteFiles from './view-all-files-delete-files.vue';
import footerUI from '@schoolboxVue/components/vueTables2/mixinFooterUI.js';

const SB_SELECTED_FILTERS_STORAGE = 'sb_viewAllFilesSelectedFilters'


export default {

    components: {
        ViewAllFilesFilters,
        ViewAllFilesDropzone,
        ViewAllFilesDeleteFiles,
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
        referrer: { //to know whether we are coming from 'component' or elsewhere
            type: String,
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
                rowClassCallback: (row) => { //to differentiate newly uploaded files so it's clear where they've been placed in the table
                    return row.isNew ? 'new-file-upload' : '';
                },
                selectable: {
                    mode: this.canEdit ? 'multiple' : '' //to enable multi-select for those with permission
                },
                sortable: ['name', 'dateModified.formatted'], //the edit column doesn't need to be sortable
                sortIcon: {
                    is: 'glyphicon-sort',
                    base: 'glyphicon',
                    up: 'glyphicon-chevron-up',
                    down: 'glyphicon-chevron-down'
                },
                tabbable: false,
                texts: {
                    count: '{from}-{to} of {count} files|{count} files|1 file',
                    filterPlaceholder: 'Search files',
                    noResults: 'No matching files',
                },
            },
            tableColumns: this.getTableColumns(),
            collator: new Intl.Collator('en', { numeric: true, sensitivity: 'base' }), //to natually sort name column
            selectedFiles: [], //keep track of which rows could be deleted/downloaded
            isShiftKeySelected: false, //to multi-select fields
            hasSelectedShiftKey: false, //to hide a UI note
        }
    },

    computed: {

        /**
         * Whether there's saved session storage and we aren't coming
         * from the component (as the component settings take precedence)
         *
         * @returns { Boolean }
         */
        useSessionStorage () {
            return sessionStorage.getItem(this.selectedFiltersStorage)
                   && this.referrer !== 'component';
        }

    },

    created () {
        this.initSelectedFilters();
    },

    mounted () {
        this.initShiftKeyDetection();
        this.initTable();
    },

    methods: {

        /**
         * The edit column is only displayed to those that have permission
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
         * Check if the filters are stored in session storage
         */
        initSelectedFilters () {
            this.selectedFiltersStorage = `${SB_SELECTED_FILTERS_STORAGE}_${this.sourceFolderId}`;
            if (this.useSessionStorage) {
                this.selectedFilters = this.getFiltersSessionStorage();
            } else {
                this.selectedFilters = [...this.presetFilters];
            }
        },

        /**
         * pull the filters from local storage
         */
        getFiltersSessionStorage () {
            const filtersSessionStorage = sessionStorage.getItem(this.selectedFiltersStorage);
            return filtersSessionStorage.split(',');
        },

        /**
         * To hide a UI note informing of shift-selection once pressed once
         */
        initShiftKeyDetection () {
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Shift') {
                    this.isShiftKeySelected = true;
                }
            });
            document.addEventListener('keyup', (event) => {
                if (event.key === 'Shift') {
                    this.isShiftKeySelected = false;
                }
            });
        },

        /**
         * Populate the table with the files list
         */
        initTable () {
            this.tableData = this.files.map((file) => file = { ...file, isNew: false } ); //add isNew so can differentiate newly uploaded files
            this.onTableLoaded();
        },

        /**
         * To update the table to show the UI in the footer
         */
        onTableLoaded () {
            this.$nextTick(() => {
                this.footerUI_update();
                this.initFilterDisplay();
                $('[data-plugin="preview"]').preview(); //Ensure the link uses the pop up modal
                if (this.canEdit) {
                    this.$refs.dropzone.initDropzone();
                }
            });
        },

        /**
         * To only show those files within the currently filtered list
         */
        initFilterDisplay () {
            if (!this.selectedFilters.includes(fileTypes.DOCUMENT)) {
                EventBus.emit(`vue-tables.filter::${fileTypes.DOCUMENT}`, fileTypes.DOCUMENT);
            }
            if (!this.selectedFilters.includes(fileTypes.AUDIO)) {
                EventBus.emit(`vue-tables.filter::${fileTypes.AUDIO}`, fileTypes.AUDIO);
            }
            if (!this.selectedFilters.includes(fileTypes.VIDEO)) {
                EventBus.emit(`vue-tables.filter::${fileTypes.VIDEO}`, fileTypes.VIDEO);
            }
            if (!this.selectedFilters.includes(fileTypes.IMAGE)) {
                EventBus.emit(`vue-tables.filter::${fileTypes.IMAGE}`, fileTypes.IMAGE);
            }
            if (!this.selectedFilters.includes(fileTypes.OTHER)) {
                EventBus.emit(`vue-tables.filter::${fileTypes.OTHER}`, fileTypes.OTHER);
            }
        },

        /**
         * To keep track of selected rows
         */
        onRowSelected (event) {
            this.selectedFiles = event;
            if (this.selectedFiles.length > 1 && this.isShiftKeySelected) { //to hide a UI note, notifying of availability of shift-select
                this.hasSelectedShiftKey = true;
            }
        },

        /**
         * Add/remove from selectedFilters and fire an event for the table
         *
         * @param { String }
         */
        onFilterChange (fileType) {
            this.updateSelectedFilters(fileType);
            this.dispatchFilterEvent(fileType);
            this.updateSessionStorage();
            this.markAllFilesAsOld();
        },

        /**
         * Add/remove from selectedFilters
         *
         * @param { String }
         */
        updateSelectedFilters (fileType) {
            if (this.selectedFilters.includes(fileType)) {
                this.selectedFilters = this.selectedFilters.filter((filterType) => filterType !== fileType);
            } else {
                this.selectedFilters = [...this.selectedFilters, fileType];
            }
        },

        /**
         * Modify what's displayed in the table, showing or hiding a filter type
         * depending on whether it's in the selected filters
         *
         * @param { String }
         */
        dispatchFilterEvent (fileType) {
            const filterParam = this.selectedFilters.includes(fileType) ? '' : fileType ;
            EventBus.emit(`vue-tables.filter::${fileType}`, filterParam);
        },

        /**
         * Save the modified filters in the session storage to allow returning to the correct
         * selected files if returning to the page from editing a file
         */
        updateSessionStorage () {
            const selectedFiltersAsString = this.selectedFilters.join(',');
            sessionStorage.setItem(this.selectedFiltersStorage, selectedFiltersAsString);
        },

        /**
         * Perform on any further user interaction, like updating filters, sorting or searching
         * Otherwise the animation used to clearly show where new files have been added will be
         * replayed on any state change
         *
         * @param { Object }
         */
        markAllFilesAsOld () {
            this.$nextTick(() => {
                this.tableData.forEach((file) => {
                    file.isNew = false;
                })
            });
        },

        /**
         * Toggle the files visibility to hidden/shown
         *
         * @param { Object }
         */
        onToggleVisibilitySelected (toggledFile) {
            //we need to do this as Vue is watching tableData, not each individual file within
            const fileInTableData = this.tableData.find((file) => file.id === toggledFile.id); 
            fileInTableData.hidden = !fileInTableData.hidden;
        },

        /**
         * Add the file to the table, ensuring it's visible by
         * checking on the relevant filter if not already active
         *
         * @param { Object }
         */
        onFileSuccess (file) {
            this.checkFileFilterIsActive(file);
            this.addFileToTable(file);
            this.$refs.table.resetQuery();
        },

        /**
         * For example, ensure the Image filter is active if a jpeg is dropped in
         * Otherwise when the file is uploaded it just looks like the file disappears
         * as it's not in the currently filtered list
         *
         * @param { Object }
         */
        checkFileFilterIsActive (file) {
            if (!this.selectedFilters.includes(file.type)) {
                this.selectedFilters = [...this.selectedFilters, file.type];
                EventBus.emit(`vue-tables.filter::${file.type}`, '');
                this.updateSessionStorage();
            }
        },

        /**
         * Add the new file into the table, marking it as new to allow the file to animate into the table to show its now
         * And add returnUrl so the edit page knows where to send the user after they have edited/deleted the file
         *
         * @param { Object }
         */
        addFileToTable (file) {
            const newFile = {
                ...file,
                isNew: true,
                editUrl: `${file.editUrl}?returnUrl=${encodeURIComponent(btoa(window.location.pathname))}`
            };
            this.tableData = [...this.tableData, newFile];
        },

        /**
         * Files have been deleted, so clear them from the table and clear the selection
         */
        onFilesDeleted () {
            this.tableData = this.getUpdatedRowsAfterUserDeletion();
            this.markAllFilesAsOld();
            this.clearRowSelection();
        },

        /**
         * To update the row display after deletion
         *
         * @returns { Array }
         */
        getUpdatedRowsAfterUserDeletion () {
            return this.tableData.filter((file) => {
                if (this.selectedFiles.some((rowToDelete) => rowToDelete.id === file.id)) { //pull out any deleted rows, those rows that are in selectedFiles, which have just been deleted
                    return false;
                }
                return true;
            });
        },

        /**
         * Clear the selection
         */
        clearRowSelection () {
            this.selectedFiles = [];
            this.$refs.table.resetSelectedRows();
        },

    },

}

</script>

<template>

    <div class="row" id="view-all-files">

        <div class="small-12 columns">

            <div class="dropzone-wrap">

                <form v-if="canEdit" class="dropzone overlay" id="dropzone" action="/cms/fileForm.php?ajax=1" method="post">
                    <div class="dz-default dz-message">You can drag files into this space.</div>
                </form>

                <v-client-table
                    ref="table"
                    :data="tableData"
                    :columns="tableColumns"
                    :options="tableOptions"
                    @select="onRowSelected"
                    @filter="markAllFilesAsOld"
                    @sorted="markAllFilesAsOld"
                >
                    <template #beforeTable>
                        <view-all-files-filters
                            :selected-filters="selectedFilters"
                            @on-filter-change="onFilterChange"
                        />
                    </template>
                    <template #name="props"> 
                        <div>
                            <a data-plugin="preview" :href="props.row.url" rel="external">
                                <h3 class="name" :style="{ opacity: props.row.hidden ? '0.3' : '1' }">
                                    <img class="mime" :src="`/static/${props.row.mime.url}`" height="32" width="32" :title="props.row.mime.description"> 
                                    {{ props.row.name }}
                                </h3>
                            </a>
                            <p class="meta">Date modified: {{ props.row.dateModified.formatted }}</p>
                        </div>
                    </template>
                    <template #edit="props">
                        <nav class="edit">
                            <button :class="`button-toggle-visibility ${ props.row.hidden ? 'icon-view' : 'icon-view-hollow' }`" @click.prevent="onToggleVisibilitySelected(props.row)" title="Toggle file visibility">Toggle file visibility</button>
                            <a class="icon-modify" :href="props.row.editUrl" title="Modify File"></a>
                        </nav>
                    </template>
                    <template #afterTable>
                        <view-all-files-dropzone
                            v-if="canEdit"
                            ref="dropzone"
                            dropzone-selector="#dropzone"
                            :source-folder-id="sourceFolderId"
                            @on-file-uploaded="onFileSuccess"
                        />
                        <div class="VueTables__table-footer" ref="tableFooter"></div>
                        <div class="component-action">
                            <section>
                                <p><a :href="`/cms/file/new/${this.sourceFolderId}`">Select a file</a> or drag and drop multiple onto this component</p>
                            </section>
                        </div>
                    </template>
                </v-client-table>

            </div>

            <view-all-files-delete-files
                v-if="canEdit"
                :selected-files="selectedFiles"
                :is-shift-key-selected="isShiftKeySelected"
                :has-selected-shift-key="hasSelectedShiftKey"
                @on-files-deleted="onFilesDeleted"
                @on-selection-cleared="clearRowSelection"
            />

        </div>

    </div>

</template>

<style lang="scss">

@import 'www/static/css/foundation/utils/_colors.scss';
@import '@schoolboxVue/components/vueTables2/vueTables2';

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

.name {
    font-size: 0.825rem;
    margin-bottom: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.meta {
    display: none;
    @media (max-width: 700px) { //show on mobile, as the .date-modified-td will be hidden
        display: block;
    }
}

.edit {
    display: flex;
    height: 2.5rem;
    text-align: center;
    width: 5rem;
}

.edit a {
    color: var(--content-ui-foreground);
    display: inline-block;
    line-height: 2.5;
    margin: 0;
    opacity: 0.7;
    padding-left: 8px; //move the pencil into the centre
}

.button-toggle-visibility {
    background: none;
    color: var(--content-ui-foreground);
    font-size: 0rem;
    line-height: 2.5;
    padding: 0px 2px 0px 10px;
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

.VueTables__table .edit-td {
    padding-left: 0; //bring it inline with the right side border
    width: 5rem; //keep the edit icon on the right side
}

.VueTables__table-footer {
    margin-bottom: 0; //.component action sits underneath
}

.component-action {
    margin-bottom: 2rem; //add margin as it's below the table-footer
}

.VueTables__table tr.new-file-upload { //have to be pretty specific to overwrite foundation rules...
    animation-duration: 2s;
    animation-iteration-count: 1;
    animation-name: fadeOutBackgroundColour;
}

@keyframes fadeOutBackgroundColour {
    0% { background-color: $success-color; }
    100% { background-color: white; }
}

</style>
