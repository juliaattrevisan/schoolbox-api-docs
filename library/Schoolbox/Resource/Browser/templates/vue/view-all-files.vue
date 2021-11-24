<script>

/**
 * Presents all the files within a homepage file component in a filterable table
 */

import filterEvents from './viewAllFilesFilterEvents.js';
import ViewAllFilesFilters from './view-all-files-filters.vue';
import ViewAllFilesRowName from './view-all-files-row-name.vue';
import ViewAllFilesRowEdit from './view-all-files-row-edit.vue';
import footerUI from '@vue/components/vueTables2/mixinFooterUI.js';

export default {

    components: {
        ViewAllFilesFilters
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
        selectedFilters: { //the list of currently selected filters passed through
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
            collator: new Intl.Collator('en', { numeric: true, sensitivity: 'base' }), //to natually sort name column
            tableData: [],
            tableOptions: { //options for vue-tables-2: https://matanya.gitbook.io/vue-tables-2/options-api
                columnsClasses: {
                    ['dateModified.formatted']: 'date-modified-td ', //to hide the column for mobile
                    edit: 'edit-td ', //to specify a width
                },
                customFilters: [ //refer to https://matanya.gitbook.io/vue-tables-2/custom-filters for further info
                    {
                        name: filterEvents.DOCUMENT_CHECKED,
                        callback: (row, query) => row.type !== query
                    },
                    {
                        name: filterEvents.AUDIO_CHECKED,
                        callback: (row, query) => row.type !== query
                    },
                    {
                        name: filterEvents.VIDEO_CHECKED,
                        callback: (row, query) => row.type !== query
                    },
                    {
                        name: filterEvents.IMAGE_CHECKED,
                        callback: (row, query) => row.type !== query
                    },
                    {
                        name: filterEvents.OTHER_CHECKED,
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
        }
    },

    mounted () {
        this.initTable();
        this.onTableLoaded();
    },

    methods: {

        /**
         * Populate the table with the files list
         */
        initTable () {
            this.tableData = this.files;
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
         *
         */
        initDropZone () {
            $('#dropzone').fileDrop({
                previewsContainer: '#view-all-files [data-dz-container]',
                previewTemplate: '#view-all-files [data-dz-template]',
                reloadContent: false,
                sending: (file, xhr, formData) => {
                    const fileDescription = file.name.substr(0, file.name.lastIndexOf('.'));
                    const uploadDescription = fileDescription.length > 0 ? fileDescription : file.name; //ensure there's a file name, otherwise just add the extension
                    formData.append('description', uploadDescription);
                    formData.append('category', this.sourceFolderId);
                    formData.append('comment', '');
                    formData.append('hidden', 0);
                },
                complete: (file) => {
                    file.previewElement.querySelector('[data-dz-thumbnail]').setAttribute('src', `/ajax/mime/${file.type}`);
                },
                success: (file, response) => {
                    this.files.push(response);
                    const previewElement = file.previewElement;
                    previewElement.remove();
                },
                error: (file, response) => {
                    document.querySelector('.dz-error [data-close]').classList.remove('hide');
                    file.previewElement.classList.remove('dz-processing');
                    file.previewElement.querySelector('[data-dz-status]').textContent = response.error;
                }
            });
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
                >
                    <div slot="beforeTable">
                        <view-all-files-filters
                            :selected-filters="selectedFilters"
                        />
                    </div>
                    <div slot="afterTable">
                        <div class="dropped-files-container">
                            <div data-dz-container=""></div>
                            <div data-dz-template="" class="hide">
                                <div>
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

.VueTables__table td {
    padding: 0.25rem 0.5rem 0.25rem 0;
    vertical-align: middle;
    @media (max-width: 700px) {
        padding: 0.5rem 0.5rem 0 0;
    }
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

</style>
