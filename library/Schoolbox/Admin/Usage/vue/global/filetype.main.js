import Vue from 'vue';

Vue.component('usageGlobalFiletype', require('./filetype.vue').default);

new Vue({
    el: '#usage-global-filetype-container'
});