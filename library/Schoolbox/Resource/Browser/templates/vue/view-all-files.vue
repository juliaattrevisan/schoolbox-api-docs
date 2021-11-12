<script>

/**
 * Presents all the files within a homepage file component in a filterable table
 */

import filterEvents from './viewAllFilesFilterEvents';
import ViewAllFilesFilters from './view-all-files-filters';
import ViewAllFilesRowName from './view-all-files-row-name';
import footerUI from '@vue/components/vueTables2/mixinFooterUI';

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
        }
    },

    data () {
        return {
            tableData: [],
            tableOptions: { //options for vue-tables-2: https://matanya.gitbook.io/vue-tables-2/options-api
                columnsClasses: {
                    ['dateModified.formatted']: 'date-modified-td ' //to hide the column for mobile
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
                },
                orderBy: { //use the passed through component instance config to match the initial sorting
                    ascending: this.componentInstanceConfig.sortdir.value === 'asc' ? true : false,
                    column: this.componentInstanceConfig.sort.value === 'name' ? 'name' : 'dateModified.formatted',
                },
                perPage: 100, 
                perPageValues: [100, 200, 300],
                resizableColumns: false,
                sortIcon: {
                    is: 'glyphicon-sort',
                    base: 'glyphicon',
                    up: 'glyphicon-chevron-up',
                    down: 'glyphicon-chevron-down'
                },
                tabbable: false,
                templates: { //refer to https://matanya.gitbook.io/vue-tables-2/templates for further info
                    name: ViewAllFilesRowName,
                },
                texts: {
                    count: 'Showing {from} to {to} of {count} files|{count} files|One file',
                    filterPlaceholder: 'Search files',
                    noResults: 'No matching files',
                },
            },
            tableColumns: ['name', 'dateModified.formatted'], //the table tableColumns
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
}

.date-modified-td {
    width: 250px; //keep on the right hand side, and don't alter width when filtering changes
    @media (max-width: 1000px) {
        width: 150px;
    }
    @media (max-width: 700px) {
        display: none; //hide on mobile, as the date will then be shown within view-all-files-name.vue
    }
}

</style>