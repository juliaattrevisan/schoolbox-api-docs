import Vue from "vue";
import AdminGrades from './adminGrades.vue';

new Vue({
    el: '#admin-grades',
    render: function (createElement) {
        return createElement(AdminGrades)
    }
});
