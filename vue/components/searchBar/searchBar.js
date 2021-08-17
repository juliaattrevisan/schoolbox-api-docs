import Vue from 'vue';
import SearchBar from './search-bar';

require('./directives/directiveClickOutside');

new Vue({
    el: '#hybrid-bar',
    components: {
        SearchBar
    }
});
