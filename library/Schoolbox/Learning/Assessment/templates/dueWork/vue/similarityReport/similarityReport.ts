import { ClientTable } from "v-tables-3-select-rows";
import SimilarityReport from "./similarity-report.vue";
import SimilarityReportNoResults from "./similarity-report-no-results.vue";

const app = window.Vue.createApp({
    components: {
        SimilarityReport,
    },
});
app.use(ClientTable, {}, "bootstrap3", {
    noResultsRow: window.Vue.markRaw(SimilarityReportNoResults), // use a custom template for no results to add in reset filter button: https://matanya.gitbook.io/vue-tables-2/custom-template
});
app.mount("#similarity-report-container");
