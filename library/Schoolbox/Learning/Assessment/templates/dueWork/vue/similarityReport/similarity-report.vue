<script>

/**
 * Show a smiliarity report for a due work, showing a list of students latest submissions.
 * The list can be filtered by similarity score and submission status
 */

import SimilarityReportFilters from './similarity-report-filters';
import BottomDrawer from '@vue/components/bottomDrawer/bottom-drawer';
import Toast from '@vue/components/toast';
import FilterEvents from './similarityReportFilterEvents';
import SimilarityReportRowStudent from './similarity-report-row-student';
import SimilarityReportRowSubmissionDate from './similarity-report-row-submission-date';
import SimilarityReportRowSimilarity from './similarity-report-row-similiarity';
import SimilarityReportRowStatus from './similarity-report-row-status';


export default {

    components: {
        SimilarityReportFilters,
        BottomDrawer,
        Toast,
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
            tableColumns: ['student', 'submissionDate', 'similarity', 'status'], //the table tableColumns
            tableOptions: { //options for vue-tables-2: https://matanya.gitbook.io/vue-tables-2/options-api
                customFilters: [ //refer to https://matanya.gitbook.io/vue-tables-2/custom-filters for further info
                    {
                        name: FilterEvents.SIMILARITY_SCORE,
                        callback: (row, filteredPercentage) => {
                            const hasFilesAboveSimilarity = row.files.some((file) => {
                                return parseInt(file.turnitin.percentage.overall) >= parseInt(filteredPercentage);
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
                    only: function (row) {
                        return row.submission.status !== 'failed'; //can't mark as resubmission required those rows that are already marked as so
                    },
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
                return 'Submitting, please wait...';
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
        this.initFilterListeners();
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
         * So we can ensure that rows that are removed through filters are also removed from the selected rows
         */
        initFilterListeners () {
            document.addEventListener(FilterEvents.SIMILARITY_SCORE, () => this.onFilterChanged());
            document.addEventListener(FilterEvents.RESUBMISSION_REQUIRED, () => this.onFilterChanged());
            document.addEventListener(FilterEvents.MARKING_REQUIRED, () => this.onFilterChanged());
            document.addEventListener(FilterEvents.REVIEWED, () => this.onFilterChanged());
        },

        /**
         * Pull any rows that are no longer visible out of the selected rows
         */
        onFilterChanged () {
            const filteredRowsIds = this.$refs.table.allFilteredData.map((filteredRow) => filteredRow.id); //is the currently visible list of rows, mapped to just the ids
            const selectedRowsIds = this.selectedRows.map((selectedRow) => selectedRow.id);
            const rowsToUnselect = selectedRowsIds.filter((selectedRowId) => !filteredRowsIds.includes(selectedRowId)); //filter out any that aren't in the filtered list 
            this.$refs.table.unselectRows(rowsToUnselect);
            this.selectedRows = this.selectedRows.filter((selectedRow) => !rowsToUnselect.includes(selectedRow.id));
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
         * Mark the selected submissions as requiring resubmission
         */
        async onRequiringResubmissionSelected () {
            this.isProcessing = true;
            this.$refs.toast.activate('Updating...');
            try {
                await Promise.all(this.getFetches());
                this.onFeedbackPublished();
                this.$refs.toast.timeout('Feedback published', true, 2000);
            } catch (error) {
                console.warn(error);
                this.$refs.toast.timeout('Unable to update', false, 3000);
            } finally {
                this.isProcessing = false;
            }
        },

        /**
         * To update all the marked submissions in parallel
         * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/all
         *
         * @returns { Array }
         */
        getFetches () {
            const fetchList = [];
            this.selectedRows.forEach((row) => {
                fetchList.push(fetch(`/learning/assessments/${this.assessmentId}/${row.user.id}/feedback`, {
                    method: 'POST',
                    body: this.getFormData(),
                }));
            });
            return fetchList;
        },

        /**
         * Send through the data to mark the submission as requiring resubmssion
         * and add the comment
         *
         * @returns { FormData }
         */
        getFormData () {
            const formData = new FormData();
            formData.append('responseId', ''); //to mark the submission as a floating comment
            formData.append('resubmissionRequired', '1');
            formData.append('comment', this.comment);
            formData.append('publish', '1');
            return formData;
        },

        /**
         * Set up for another possible update
         */
        onFeedbackPublished () {
            this.comment = ''; //clear the comment for another possible entry
            this.markRowsAsRequiringResubmission();
            this.clearRows();
        },

        /**
         * Loop through and update the submissions
         */
        markRowsAsRequiringResubmission () {
            this.selectedRows.forEach((selectedRow) => { //directly edit the original data to update the display
                const rowData = this.tableData.find((row) => selectedRow.user.id === row.user.id);
                rowData.submission.status = 'failed';
            });
        },

        /**
         * Clear the selection
         */
        clearRows () {
            this.selectedRows = [];
            this.$refs.table.resetSelectedRows(); //ensure vue-tables-2 updates
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

            <toast ref="toast"></toast>

        </div>

    </div>

</template>

<style lang="scss">

@import '@vue/components/vueTables2/vueTables2';
@import './similarity-report';

.VuePagination {
    display: none; //there's no footer, so no count/pagination/next/prev etc for this report
}

@media (max-width: $tableToDoubleColumn) { //display everything in two columns
    .VueTables thead {
        display: none;
    }
    .VueTables__row td:not(.VueTables__select-row) {
        border-bottom: none;
        border-top: 1px solid var(--body-background);
        display: inline-block;
        width: 50%;
    }
    .VueTables__row td.VueTables__select-row {
        border-bottom: none;
        border-top: 2px solid var(--body-background);
        display: block;
        width: 100%;
    }
}

@media (max-width: $tableToSingleColumn) { //and then display everything in one column
    .VueTables__row td:not(.VueTables__select-row) {
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
    background: none;
    font-size: 0.8rem;
    padding: 0px;
    vertical-align: baseline;
    &:hover {
        background: none;
    }
}

.comment-field {
    min-height: 4rem;
}

</style>
