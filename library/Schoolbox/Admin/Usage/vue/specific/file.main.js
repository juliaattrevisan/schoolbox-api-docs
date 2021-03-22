import Vue from 'vue';

Vue.component('usageSpecificFile', require('./file.vue').default);

new Vue({
    el: '#usage-specific-file-container'
});