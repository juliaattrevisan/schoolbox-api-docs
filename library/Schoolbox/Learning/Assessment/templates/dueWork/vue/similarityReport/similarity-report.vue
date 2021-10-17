<script>

/**
 * Show a smiliarity report for a due work, showing a list of students latest submissions. 
 * The list can be filtered by similarity score and submission status
 */

import SimilarityReportFilters from './similarity-report-filters';
import FilterEvents from './similarityReportFilterEvents';
import footerUI from '@vue/components/vueTables2/mixinFooterUI';
import SimilarityReportRowStudent from './similarity-report-row-student';
import SimilarityReportRowSubmissionDate from './similarity-report-row-submission-date';
import SimilarityReportRowSimilarity from './similarity-report-row-similiarity';
import SimilarityReportRowStatus from './similarity-report-row-status';


export default {

  components: {
    SimilarityReportFilters
  },

  mixins: [
    footerUI //to place and keep the tables UI in the footer
  ],
  
  props: {
    payloadUrl: { //where to get the data from
      type: String,
      required: true
    },
  },
  
  data () {
    return {
      isDataLoaded: false, //show loading display until true
      hasDataLoadError: false, //display load error
      data: [], //hold all student data
      columns: ['student', 'submissionDate', 'similarity',  'status'], //the table columns
      tableOptions: { //options for vue-tables-2: https://matanya.gitbook.io/vue-tables-2/options-api
        customFilters: [
          {    
            name: FilterEvents.SIMILARITY_SCORE,    
            callback: (row, filteredPercentage) => { 
              const hasFilesAboveSimilarity = row.files.some((file) => {
                return parseInt(file.turnitin.percentage) >= parseInt(filteredPercentage);
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
        orderBy: {
          ascending: true,
          column: 'student' //initial sorting display
        },
        perPage: 50, 
        perPageValues: [50, 100, 200],
        resizableColumns: false,
        sortable: ['student', 'submissionDate'], //only student and submission date are sortable
        sortIcon: {
          is: 'glyphicon-sort',
          base: 'glyphicon',
          up: 'glyphicon-chevron-up',
          down: 'glyphicon-chevron-down'
        },
        tabbable: false,
        templates: {      
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
        uniqueKey: 'user.id', //the default is 'id', so need to update to point to the user object
      },
    }
  },

  computed: {

    /**
     * To show a note showing no students have submitted yet
     * 
     * @returns { Boolean }
     */
    hasNoSubmissions () {
      return this.isDataLoaded && !this.hasDataLoadError && this.data.length === 0;
    },

    /**
     * Can show the table
     * 
     * @returns { Boolean }
     */
    hasSubmissions () {
      return this.isDataLoaded && !this.hasDataLoadError && this.data.length > 0;
    },

  },

  mounted () {
    this.getData();
  },

  methods: {

    /**
     * Load the current student data
     */
    async getData () {
      try {
        const response = await fetch(this.payloadUrl);
        const responseData = await response.json();
        this.data = responseData;
      } catch (error) {
        console.warn(error);
        this.hasDataLoadError = true; //show load error
      } finally {
        this.isDataLoaded = true; //update the display to show the list
      }
    },

    /**
     * To update the table to show the UI in the footer, the pagination buttons
     */
    onLoaded () {
      this.$nextTick(() => this.footerUI_update());
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
        :data="data" 
        :columns="columns" 
        :options="tableOptions" 
        @loaded="onLoaded" 
      >
        <div slot="beforeTable">
          <similarity-report-filters />
        </div>
        <div slot="afterTable">
          <div class="VueTables__table-footer" ref="tableFooter"></div>
        </div>
      </v-client-table>

      <div v-else-if="hasNoSubmissions" class="empty-state">
        <i class="icon-staff-students"></i>
        <p>Students have not yet submitted any work. Once submitted you may return to this page to review the similarity results for each submission.</p>
      </div>

      <div v-else-if="hasDataLoadError">
        <section class="content">
          <div data-alert class="alert-box alert">
            Sorry, we are currently unable to load the Similarity Report. Please contact Schoolbox Support it the problem persists. 
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

    </div>

  </div>

</template>

<style lang="scss">

@import '@vue/components/vueTables2/vueTables2';

</style>