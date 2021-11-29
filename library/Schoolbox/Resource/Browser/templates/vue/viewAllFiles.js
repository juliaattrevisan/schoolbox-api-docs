import Vue from 'vue';
import { ClientTable } from 'vue-tables-2-premium';
import ViewAllFiles from './view-all-files';
// import SimilarityReportNoResults from './similarity-report-no-results';

//use a custom template for no results to add in reset filter button: https://matanya.gitbook.io/vue-tables-2/custom-template
// Vue.use(ClientTable, {}, false, 'bootstrap3', {
//     noResultsRow: SimilarityReportNoResults
// });

Vue.use(ClientTable);

new Vue({
    el: '#view-all-files-container',
    components: {
        ViewAllFiles
    }
});