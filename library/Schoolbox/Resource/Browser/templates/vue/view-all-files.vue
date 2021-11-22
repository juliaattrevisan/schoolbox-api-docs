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
        canEdit: { //whether the user has permission to modify files
            type: Boolean,
            required: true,
        }
    },

    data () {
        return {
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
            this.$nextTick(() => this.footerUI_update());
        },

    },

}

</script>

<template>

    <div class="row">

        <div class="small-12 columns">

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
                    <div class="VueTables__table-footer" ref="tableFooter"></div>
                </div>
            </v-client-table>

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


</style>