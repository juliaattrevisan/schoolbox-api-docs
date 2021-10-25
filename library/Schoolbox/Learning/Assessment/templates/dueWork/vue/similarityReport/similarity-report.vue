<script>

/**
 * Show a smiliarity report for a due work, showing a list of students latest submissions.
 * The list can be filtered by similarity score and submission status
 */

import SimilarityReportFilters from './similarity-report-filters';
import BottomDrawer from '@vue/components/bottomDrawer/bottom-drawer';
import FilterEvents from './similarityReportFilterEvents';
import SimilarityReportRowStudent from './similarity-report-row-student';
import SimilarityReportRowSubmissionDate from './similarity-report-row-submission-date';
import SimilarityReportRowSimilarity from './similarity-report-row-similiarity';
import SimilarityReportRowStatus from './similarity-report-row-status';
import { timeout } from '@js/util/jsUtils';


export default {

    components: {
        SimilarityReportFilters,
        BottomDrawer,
    },

    props: {
        assessmentId: { //to mark submissions as requiring resubmission
            type: Number,
            required: true
        },
        payloadUrl: { //where to get the tableData from
            type: String,
            required: true
        },
    },

    data () {
        return {
            isDataLoaded: false, //show loading display until true
            hasDataLoadError: false, //display load error
            tableData: [], //hold all student tableData
            tableColumns: ['student', 'submissionDate', 'similarity',    'status'], //the table tableColumns
            tableOptions: { //options for vue-tables-2: https://matanya.gitbook.io/vue-tables-2/options-api
                customFilters: [ //refer to https://matanya.gitbook.io/vue-tables-2/custom-filters for further info
                    {
                        name: FilterEvents.SIMILARITY_SCORE,
                        callback: (row, filteredPercentage) => {
                            const hasFilesAboveSimilarity = row.files.some((file) => {
                                return parseInt(file.turnitin.percentage.overall) >= parseInt(filteredPercentage.overall);
                            });
                            return hasFilesAboveSimilarity;
                        }
                    },
                    {
                        name: FilterEvents.RESUBMISSION_REQUIRED,
                        callback: (row, query) => {
                            return row.submission.status !== query;
                        }
                    },
                    {
                        name: FilterEvents.MARKING_REQUIRED,
                        callback: (row, query) => {
                            return row.submission.status !== query;
                        }
                    },
                    {
                        name: FilterEvents.REVIEWED,
                        callback: (row, query) => {
                            return row.submission.status !== query;
                        }
                    },
                ],
                customSorting: {
                    student: (ascending) => { //sort student by lastname
                        return (a, b) => {
                            if (ascending) {
                                return a.user.lastname >= b.user.lastname ? 1 : -1;
                            }
                            return a.user.lastname <= b.user.lastname ? 1 : -1;
                        }
                    },
                    submissionDate: (ascending) => { //sort submission date accurately by the timestamp
                        return (a, b) => {
                            if (ascending) {
                                return a.submission.submitted_at.timestamp >= b.submission.submitted_at.timestamp ? 1 : -1;
                            }
                            return a.submission.submitted_at.timestamp <= b.submission.submitted_at.timestamp ? 1 : -1;
                        }
                    }
                },
                filterable: false, //this hides the search input field, the custom filters still function as expected
                headings: {
                    student: 'Student',
                    submissionDate: 'Submission date',
                    similarity: 'Similarity %',
                    status: 'Status',
                },
                orderBy: { //initial sorting display
                    ascending: true,
                    column: 'student'
                },
                perPage: 9999, //absurdly large value to avoid pagination as there's no footer
                resizableColumns: false,
                selectable: { //to enable row selection
                    mode: 'multiple',
                    // only: function (row) {
                    //     return row.submission.status !== 'failed'; //can't mark as resubmission required those rows that are already marked as so
                    // },
                },
                sortable: ['student', 'submissionDate'], //only student and submission date are sortable
                sortIcon: {
                    is: 'glyphicon-sort',
                    base: 'glyphicon',
                    up: 'glyphicon-chevron-up',
                    down: 'glyphicon-chevron-down'
                },
                tabbable: false,
                templates: { //refer to https://matanya.gitbook.io/vue-tables-2/templates for further info
                    student: SimilarityReportRowStudent,
                    submissionDate: SimilarityReportRowSubmissionDate,
                    similarity: SimilarityReportRowSimilarity,
                    status: SimilarityReportRowStatus,
                },
                texts: {
                    noResults: 'No matching students...',
                    count: '{from}-{to} of {count}',
                    limit: 'Rows per page:'
                },
            },
            comment: '', //the message coupled with the mark as requiring resubmission
            singleRowName: 'submission',
            pluralRowName: 'submissions',
            selectedRows: [], //keep track of which rows could be deleted
            isProcessing: false,
        }
    },

    computed: {

        /**
         * To show a note showing no students have submitted yet
         *
         * @returns { Boolean }
         */
        hasNoSubmissions () {
            return this.isDataLoaded && !this.hasDataLoadError && this.tableData.length === 0;
        },

        /**
         * Can show the table
         *
         * @returns { Boolean }
         */
        hasSubmissions () {
            return this.isDataLoaded && !this.hasDataLoadError && this.tableData.length > 0;
        },

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

        /**
         * To update when processing/updated the form
         *
         * @returns { String }
         */
        submitButtonText () {
            if (this.isProcessing) {
                return 'Processing, please wait...';
            } else {
                return 'Mark as resubmission required';
            }
        },

        /**
         *  Prevent further selection when already processing
         *
         * @returns { Boolean }
         */
        isSubmitDisabled () {
            return this.isProcessing;
        },

    },

    mounted () {
        this.getTableData();
    },

    methods: {

        /**
         * Load the current student tableData
         */
        async getTableData () {
            try {
                const response = await fetch(this.payloadUrl);
                const responseData = await response.json();
                this.tableData = responseData;
            } catch (error) {
                console.warn(error);
                this.hasDataLoadError = true; //show load error
            } finally {
                this.isDataLoaded = true; //update the display to show the list
            }
        },

        /**
         * to keep track of selected rows
         *
         * @param { Object }
         */
        onRowSelected (selectedRows) {
            this.selectedRows = selectedRows;
        },

        /**
         * Delete the selected users, informing of deletion and clearing them from the table list as well
         *
         */
        async onRequiringResubmissionSelected () {
            this.isProcessing = true;
            try {
                const promises = [];
                this.selectedRows.forEach((row) => {
                    promises.push(fetch(`/learning/assessments/${this.assessmentId}/${row.user.id}/feedback`, {
                        method: 'POST',
                        body: this.getFormData(),
                    }));
                });
                await Promise.all(promises);
                this.isProcessing = false;
                this.comment = '';
                this.clearRows();
            } catch (error) {
                this.isProcessing = false;
                console.warn(error);
                // this.$refs.toast.timeout('Unable to delete', false, 2000);
            }
        },


        /**
         * Send through the data to delete the row
         *
         * @returns { FormData }
         */
        getFormData () {
            const formData = new FormData();
            // formData.append('resubmissionRequired', '1');
            formData.append('comment', this.comment);
            // formData.append('publish', '1');
            return formData;
        },

        /**
         * clear the selection
         */
        clearRows () {
            this.selectedRows = [];
            this.$refs.table.resetSelectedRows();
        },

        /**
         * To clear selected users
         */
        onClearSelected () {
            this.clearRows();
        },

    }

}
</script>

<template>

    <div class="row">

        <div class="small-12 island">

            <v-client-table
                v-if="hasSubmissions"
                ref="table"
                :data="tableData"
                :columns="tableColumns"
                :options="tableOptions"
                @select="onRowSelected"
            >
                <div slot="beforeTable">
                    <similarity-report-filters />
                </div>
            </v-client-table>

            <div v-else-if="hasNoSubmissions" class="empty-state">
                <i class="icon-staff-students"></i>
                <p>Students have either not yet submitted any work or any similarity results are yet to be generated.<br>Once work is submitted and results generated, you may return to this page to review the similarity results for each submission.</p>
            </div>

            <div v-else-if="hasDataLoadError">
                <section class="content">
                    <div tableData-alert class="alert-box alert">
                        Sorry, we are currently unable to load the similarity report. Please contact your support it the problem persists.
                    </div>
                </section>
            </div>

            <div v-else>
                <table class="skeleton">
                    <thead>
                        <tr>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td></td>
                        </tr>
                        <tr>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <bottom-drawer :showDrawer="selectedRowsCount > 0">
                <h3 class="selected-count"><em>{{ selectedRowsCountWithRowName }} selected</em></h3>
                <button @click.prevent="onClearSelected" @keydown.enter="onClearSelected" class="button-clear">Clear selection</button>
                <label>Comment</label>
                <textarea class="comment-field" v-model="comment"></textarea>
                <button @click.prevent="onRequiringResubmissionSelected" @keydown.enter="onRequiringResubmissionSelected" class="button submit button-delete" :disabled="isSubmitDisabled">{{ submitButtonText }}</button>
            </bottom-drawer>

        </div>

    </div>

</template>

<style lang="scss">

@import '@vue/components/vueTables2/vueTables2';
@import './similarity-report';

.VuePagination {
    display: none; //there's no footer, so no count/pagination/next/prev etc for this report
}

@media (max-width: $tableToSingleColumn) { //display everything in one column
    .VueTables thead {
        display: none;
    }
    .VueTables .VueTables__row td {
        display: block;
        padding: 0.5rem 0;
        width: 100%;
    }
}

.selected-count {
    display: inline-block; //so .button-clear can sit on the same line
    margin-right: 1rem;
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

.comment-field {
    min-height: 4rem;
}

</style>
