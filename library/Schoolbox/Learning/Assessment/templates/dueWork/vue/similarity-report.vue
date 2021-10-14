<script>

/**
 * Show a smiliarity report for a due work, showing a list of students latest submissions. 
 * The list can be filtered by similarity score and submission status
 */

import SimilarityReportFilters from './similarity-report-filters';


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
      rows: [], //hold all student data
      // columns: ['id', 'date', 'similarity', 'files', 'status'],
      columns: ['user.id', 'submission.submitted_at.nice', 'files', 'files.turnitin', 'submission.status'],
      tableOptions: {
        headings: {
          ['user.id']: 'Student',
          ['submission.submitted_at.nice']: 'Submission date',
          files: 'Similarity %',
          'files.turnitin': 'File(s)',
          ['submission.status']: 'Status',
        },
        // orderBy: {
        //     ascending: false,
        //     column: 'updated_at' //initial sorting display
        // },
        // templates: {
        //     updated_at: (h, row, index) => {
        //         return dayjs(row.updated_at).format(this.dateFormat); //to format date properly
        //     }
        // },
        // perPage: 50, 
        // perPageValues: [50, 100, 200],
        // selectable: {
        //     mode: 'multiple' //to enable multi-select
        // },
        // resizableColumns: false,
        // tabbable: false,
        // texts: {
        //     filterPlaceholder: 'Search for users...',
        //     noResults: 'No matching users...',
        //     count: '{from}-{to} of {count}',
        //     limit: 'Rows per page:'
        // },
        // sortIcon: {
        //     is: 'glyphicon-sort',
        //     base: 'glyphicon',
        //     up: 'glyphicon-chevron-up',
        //     down: 'glyphicon-chevron-down'
        // },
        // customSorting: {
        //     year: (ascending) => { //sort year level naturally
        //         return (a, b) => {
        //             if (ascending) {
        //                 return this.collator.compare(a.year, b.year);
        //             } else {
        //                 return this.collator.compare(b.year, a.year);
        //             }
        //         }
        //     }
        // }
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
      return this.isDataLoaded && this.rows.length === 0;
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
        this.rows = responseData;
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
        :data="rows" 
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

<style lang="scss" scoped>


</style>