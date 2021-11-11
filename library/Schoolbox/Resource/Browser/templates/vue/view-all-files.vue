<script>

/**
 * Presents all the files within a homepage file component in a filterable table
 */

import ViewAllFilesRowName from './view-all-files-row-name.vue';

export default {
    
    props: {
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
            isDataLoaded: false, //show loading display until true
            hasDataLoadError: false, //display load error
            tableData: [], //hold all student tableData
            tableColumns: ['name', 'dateModified.formatted'], //the table tableColumns
            tableOptions: { //options for vue-tables-2: https://matanya.gitbook.io/vue-tables-2/options-api
                // customFilters: [ //refer to https://matanya.gitbook.io/vue-tables-2/custom-filters for further info
                //     {
                //         name: FilterEvents.SIMILARITY_SCORE,
                //         callback: (row, filteredPercentage) => {
                //             const hasFilesAboveSimilarity = row.files.some((file) => {
                //                 return parseInt(file.turnitin.percentage.overall) >= parseInt(filteredPercentage);
                //             });
                //             return hasFilesAboveSimilarity;
                //         }
                //     },
                //     {
                //         name: FilterEvents.RESUBMISSION_REQUIRED,
                //         callback: (row, query) => {
                //             return row.submission.status !== query;
                //         }
                //     },
                //     {
                //         name: FilterEvents.MARKING_REQUIRED,
                //         callback: (row, query) => {
                //             return row.submission.status !== query;
                //         }
                //     },
                //     {
                //         name: FilterEvents.REVIEWED,
                //         callback: (row, query) => {
                //             return row.submission.status !== query;
                //         }
                //     },
                // ],
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
                // filterable: false, //this hides the search input field, the custom filters still function as expected
                debounce: 100, //sorter debounce as data is stored locally
                headings: {
                    name: 'Name',
                    ['dateModified.formatted']: 'Date modified',
                },
                orderBy: { //initial sorting display
                    ascending: true,
                    column: 'name'
                },
                perPage: 9999, //absurdly large value to avoid pagination as there's no footer
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
                    filterPlaceholder: 'Search files',
                    noResults: 'No matching files',
                },
            },
            isProcessing: false,
        }
    },

    mounted () {
        this.tableData = this.files;
    },

}

</script>

<template>

    <div class="row">

        <v-client-table
            :data="tableData"
            :columns="tableColumns"
            :options="tableOptions"
        >
            <!-- <div slot="beforeTable">
                <similarity-report-filters />
            </div> -->
        </v-client-table>

    </div>

</template>

<style lang="scss">

@import '@vue/components/vueTables2/vueTables2';

.VuePagination {
    display: none; //there's no footer, so no count/pagination/next/prev etc for this report
}

</style>