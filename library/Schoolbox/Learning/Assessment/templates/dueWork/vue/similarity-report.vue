<script>

/**
 * Show a smiliarity report for a due work, showing a list of students latest submissions. 
 * The list can be filtered by similarity score and submission status
 */

import SimilarityReportFilters from './similarity-report-filters';
import SimilarityReportRowStudent from './similarity-report-row-student';
import SimilarityReportRowSubmissionDate from './similarity-report-row-submission-date';


export default {

  components: {
    SimilarityReportFilters
  },
  
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
      columns: ['student', 'submission.submitted_at', 'files', 'files.turnitin', 'submission.status'],
      tableOptions: {
        headings: {
          student: 'Student',
          'submission.submitted_at': 'Submission date',
          files: 'Similarity %',
          'files.turnitin': 'File(s)',
          'submission.status': 'Status',
        },
        templates: {      
          student: SimilarityReportRowStudent,
          'submission.submitted_at': SimilarityReportRowSubmissionDate,
        },
        uniqueKey: 'user.id', //the default is id, so need to update
        // perPage: 50, 
        // perPageValues: [50, 100, 200],
        // selectable: {
        //     mode: 'multiple' //to enable multi-select
        // },
        resizableColumns: false,
        tabbable: false,
        // texts: {
        //     filterPlaceholder: 'Search for users...',
        //     noResults: 'No matching users...',
        //     count: '{from}-{to} of {count}',
        //     limit: 'Rows per page:'
        // },
        orderBy: {
          ascending: true,
          column: 'student' //initial sorting display
        },
        sortable: ['student', 'submission.submitted_at'], //only student and submission date is sortable
        customSorting: {
          student: (ascending) => { //sort student by lastname
            return (a, b) => {
              if (ascending) {
                return a.user.lastname >= b.user.lastname ? 1 : -1; 
              } 
              return a.user.lastname <= b.user.lastname ? 1 : -1;                         
            }
          },
          'submission.submitted_at.nice': (ascending) => { //sort submission date accurately by the timestamp
            return (a, b) => {
              if (ascending) {
                return a.submission.submited_at.timestamp >= b.submission.submited_at.timestamp ? 1 : -1;
              }
              return a.submission.submited_at.timestamp <= b.submission.submited_at.timestamp ? 1 : -1;
            }                     
          }
        },
        sortIcon: {
          is: 'glyphicon-sort',
          base: 'glyphicon',
          up: 'glyphicon-chevron-up',
          down: 'glyphicon-chevron-down'
        },
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
      return this.isDataLoaded && this.data.length === 0;
    }

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

  }

}
</script>

<template>

  <div class="row">
    <div class="small-12 columns">

      <div v-if="hasNoSubmissions" class="empty-state">
        <i class="icon-staff-students"></i>
        <p>Students have not yet submitted any work. Once submitted you may return to this page to review the similarity results for each submission.</p>
      </div>

      <!-- <similarity-report-filters /> -->

      <v-client-table 
        v-if="isDataLoaded"
        ref="table"
        :data="data" 
        :columns="columns" 
        :options="tableOptions" 
      >
        <!-- <div slot="afterTable">
          <div class="VueTables__table-footer" ref="tableFooter"></div>
        </div> -->
      </v-client-table>

    </div>
  </div>

</template>

<style lang="scss">

@import '@vue/components/vueTables2/vueTables2';

</style>