import Vue from 'vue';

Vue.component('usageSpecificFiletype', require('./filetype.vue').default);

new Vue({
    el: '#usage-specific-filetype-container'
});