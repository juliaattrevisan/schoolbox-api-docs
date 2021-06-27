<script>
import axios from "axios";
import BadgeImage from '../../vue/badge-image';
import RevealModal from '@schoolbox/reveal-modal'
import toastMixin from '@schoolbox/toast-mixin';

/* Vue select
 */
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";

/* Render custom template for Deselect icon/button
 * It's not well documented, but you can trawl through closed PRs to figger out how
 * https://github.com/sagalbot/vue-select/issues/994
 */
import Deselect from '@schoolbox/selectize/Deselect';
import qs from "qs";
vSelect.props.components.default = () => ({ Deselect });

/**
 * Modal for withdrawing a single goal from a single student.
 */
export default {

    /**
     * Name.
     * The name of the component that we have here. This name will be used in the container
     * to spawn this component.
     */
    name: "badge-goal-withdraw-modal",

    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     */
    mixins: [
        toastMixin
    ],

    /**
     * Props.
     * Immutable props that were passed to this component, usually via twig, in our setup.
     * These are the hyphen-separated vars which are magically computed into camelcase elsewhere.
     */
    props: [
    ],

    /**
     * Components.
     * Any other vue components you also want to use here; they must already be imported
     * at the top of the file.
     */
    components: {
        BadgeImage,
        RevealModal,
        vSelect
    },

    /**
     * Data.
     * Mutable properties that can be accessed from within this component via "this" keyword
     * Note that v-if and v-for must never be on the same node together, as this overrides
     * the meaning of "this" in a way you don't expect.
     */
    data() {
        return {
            badge: null,
            loading: true,

            /* Vue Select
             */
            options: [],
            selected: [],
        }
    },

    /**
     * Mounted.
     * Basically, when vue has done all its inits and is ready to go
     * kind of like window.onload
     */
    mounted: function () {
    },

    /**
     * Computed properties.
     * Access the results of a function as if they were a property of an object.
     */
    computed: {
    },

    /**
     * Methods.
     * Arbitrary javascript functions which operate on the args provided, just like any
     * other ordinary javascript that you might have written before.
     */
    methods: {

        /**
         * Open this modal.
         */
        open(badge) {
            this.badge = badge;

            /* Clear selectize
             */
            this.options = [];
            this.selected = [];

            this.$refs.modal.open();
        },

        /**
         * Close this modal
         */
        close() {
            this.$refs.modal.close();
        },

        /**
         * Start searching for keyword
         *
         * @param query
         * @param loading
         */
        onSearch(query, loading) {
            if (query.length < 3) {
                return;
            }
            loading(true);
            this.search(loading, query, this);
        },

        /**
         * Perform async request and load results into component
         */
        search: _.debounce(function(loading, query, vm) {
            axios.get(this.badge._links.goalRecipients, {
                params: {
                    query: query
                }
            })
            .then(function(response) {
                vm.options = response.data;
                loading(false);
            });
        }, 350),

        /**
         * Perform goal withdrawal from selected users
         */
        withdraw() {

            /* Nobody selected - nothing to do
             */
            if (!this.selected.length) {
                return;
            }

            let self = this;

            /* Mine $selected for ids
             */
            let userIds = [];

            for (let i = 0; i < this.selected.length; i++) {
                userIds.push(this.selected[i].id);
            }

            /* Pack it up and send it
             */
            let request = {
                url: this.badge._links.goalWithdraw,
                data:  qs.stringify({userIds: userIds}),
                method: 'post',
                responseType: 'json'
            }

            /* A toast - to your valour, my sword, and our victory together
             */
            self.toastActivate('Removing goal for ' + this.selected.length + ' student(s)');

            axios.request(request)
                .then(function (response) {

                    /* Close this modal
                     */
                    self.close();

                    /* Pop toast
                     */
                    self.toastSuccess('Goal has been removed');
                })
                .then(function() {

                    /* Clear selection after async outcome
                     */
                    self.selected = [];
                })
                .catch(error => {
                    if (error.response) {
                        if (error.response) {
                            self.toastError('Unable to remove goal; please try again.');
                        }
                    }
                });
        }
    }
}
</script>
<template>
    <reveal-modal ref="modal">
        <template v-if="badge">
            <h2>Remove <strong>'{{ badge.name }}'</strong> goal</h2>

            <div class="card small-12 compact badge-card">
                <div>
                    <badge-image :badge="badge"></badge-image>
                    <h3>{{ badge.name }}</h3>
                </div>
            </div>

            <v-select class="schoolbox-vue-selectize"
                      :options="options"
                      :clear-search-on-blur="function() { return true; }"
                      :clear-search-on-select="true"
                      :close-on-select="false"
                      multiple
                      @search="onSearch"
                      label="name"
                      placeholder="Search for users to remove goal from"
                      v-model="selected">
                <template slot="option" slot-scope="option">
                    <div class="d-center">
                        {{ option.name }}
                    </div>
                </template>
                <template slot="selected-option" slot-scope="option">
                    <div class="selected d-center">
                       {{ option.name }}
                    </div>
                </template>
            </v-select>

            <ul class="flex-list buttons">
                <li><a href="#" class="submit" @click.prevent="withdraw()" v-bind:class="{ disabled: !this.selected.length}">Remove goal</a></li>
                <li><a href="#" class="button" @click.prevent="close()">Cancel</a></li>
            </ul>
        </template>
    </reveal-modal>
</template>
