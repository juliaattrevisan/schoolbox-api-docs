import Vue from "vue";
import AwardList from './award-list';

Vue.component('award-list', AwardList);
window.awardList = new Vue({
    el: '#award-list-container'
});
