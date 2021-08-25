import Vue from "vue";
import BadgeListForm from './badgeListForm';

Vue.component('badge-list-form', BadgeListForm);
window.badgeList = new Vue({
    el: '#badge-list-form-container'
});
