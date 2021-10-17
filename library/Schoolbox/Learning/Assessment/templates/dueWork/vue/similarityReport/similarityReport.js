import Vue from 'vue';
import { ClientTable } from 'vue-tables-2';
import SimilarityReport from './similarity-report';

Vue.use(ClientTable);

new Vue({
  el: '#similarity-report-container',
  components: {
    SimilarityReport
  }
});