import { createApp, markRaw } from 'vue';
import SimilarityReport from './similarity-report';
import SimilarityReportNoResults from './similarity-report-no-results';
import { ClientTable } from 'v-tables-3-select-rows';

const app = createApp({
    components: {
        SimilarityReport
    }
});
app.use(ClientTable);
// app.use(ClientTable, {}, false, 'bootstrap3', {
//     noResultsRow: markRaw(SimilarityReportNoResults) //use a custom template for no results to add in reset filter button: https://matanya.gitbook.io/vue-tables-2/custom-template
// });
app.mount('#similarity-report-container');