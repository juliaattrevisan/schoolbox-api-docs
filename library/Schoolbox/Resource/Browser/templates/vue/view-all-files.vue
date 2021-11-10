<script>

/**
 * 
 */

export default {
    
    props: {
        files: {
            type: Array,
            required: true,
        },
        filters: {
            type: Array,
            required: true,
        }
    },

    data () {
        return {
            isDataLoaded: false, //show loading display until true
            hasDataLoadError: false, //display load error
            tableData: [], //hold all student tableData
            tableColumns: ['name', 'dateAdded'], //the table tableColumns
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
                // customSorting: {
                //     student: (ascending) => { //sort student by lastname
                //         return (a, b) => {
                //             if (ascending) {
                //                 return a.user.lastname >= b.user.lastname ? 1 : -1;
                //             }
                //             return a.user.lastname <= b.user.lastname ? 1 : -1;
                //         }
                //     },
                //     submissionDate: (ascending) => { //sort submission date accurately by the timestamp
                //         return (a, b) => {
                //             if (ascending) {
                //                 return a.submission.submitted_at.timestamp >= b.submission.submitted_at.timestamp ? 1 : -1;
                //             }
                //             return a.submission.submitted_at.timestamp <= b.submission.submitted_at.timestamp ? 1 : -1;
                //         }
                //     }
                // },
                // filterable: false, //this hides the search input field, the custom filters still function as expected
                headings: {
                    name: 'Name',
                    dateAdded: 'Date added',
                },
                // orderBy: { //initial sorting display
                //     ascending: true,
                //     column: 'student'
                // },
                perPage: 9999, //absurdly large value to avoid pagination as there's no footer
                resizableColumns: false,
                sortIcon: {
                    is: 'glyphicon-sort',
                    base: 'glyphicon',
                    up: 'glyphicon-chevron-up',
                    down: 'glyphicon-chevron-down'
                },
                tabbable: false,
                // templates: { //refer to https://matanya.gitbook.io/vue-tables-2/templates for further info
                //     student: SimilarityReportRowStudent,
                //     submissionDate: SimilarityReportRowSubmissionDate,
                //     similarity: SimilarityReportRowSimilarity,
                //     status: SimilarityReportRowStatus,
                // },
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