<script>

import vSelect from "vue-select";
import axios from "axios";
import debounce from "lodash/debounce";

import "vue-select/dist/vue-select.css";

/* Render custom template for Deselect icon/button
 * It's not well documented, but you can trawl through closed PRs to figger out how
 * https://github.com/sagalbot/vue-select/issues/994
 */
import Deselect from '@schoolbox/selectize/Deselect';
vSelect.props.components.default = () => ({ Deselect });

export default {
    components: {vSelect},
    props: {
        badge: {
            type: Object,
            default: {},
        },
    },
    data() {
        return {
            options: [],
            selected: [],
        }
    },
    methods: {
        search: debounce(function(loading, query, vm) {
            axios.get('/ajax/user.php', {
                params: {
                    query: query,
                    groupID: 0
                }
            })
            .then(function(response) {
                vm.options = response.data;
                loading(false);
            });
        }, 350),

        /**
         * @param {String} query
         * @param {Function} loading
         */
        onSearch(query, loading) {
            loading(true);
            this.search(loading, query, this);
        },


    },
}
</script>
<template>
    <div>
        <h2>Awarding &lsquo;<i>{{ badge.name }}</i>&rsquo;</h2>
        <v-select class="schoolbox-vue-selectize"
                  :options="options"
                  :clear-search-on-blur="function() { return true; }"
                  :clear-search-on-select="false"
                  :close-on-select="false"
                  multiple
                  @search="onSearch"
                  label="name"
                  placeholder="Search for people to invite"
                  v-model="selected">
            <template slot="option" slot-scope="option">
                <span class="d-center">
                    {{ option.name }}
                </span>
            </template>
            <template slot="selected-option" slot-scope="option">
                <span class="selected d-center">
                    {{ option.first_name }} {{ option.last_name }}
                </span>
            </template>
        </v-select>
    </div>
</template>
