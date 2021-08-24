import Vue from "vue";
import AwardList from './awardList';

Vue.component('award-list', AwardList);
window.awardList = new Vue({
    el: '#award-list-container'
});
