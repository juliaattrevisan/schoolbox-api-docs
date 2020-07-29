<script>
    import axios from "axios";
    import Paginate from 'vuejs-paginate'
    import {ENDPOINTS} from '@schoolboxCommsNews/Statistics/templates/visitation/endpoints.js';

    export default {

        /**
         * Name.
         * The name of the component that we have here. This name will be used in the container
         * to spawn this component.
         */
        name: "visitation-list-full",

        /**
         * Mixins.
         * Basically traits for vue; allows you to share javascript between components in the same
         * manner as a php trait. They must already be imported at the top of the file and will
         * be superpositioned on top of this javascript here, inside export default {}
         */
        mixins: [],

        /**
         * Props.
         * Immutable props that were passed to this component, usually via twig, in our setup.
         * These are the hypen-separated vars which are magically computed into camelcase elsewhere.
         */
        props: [
            'payload',
        ],

        /**
         * Components.
         * Any other vue components you also want to use here; they must already be imported
         * at the top of the file.
         */
        components: {
            'Paginate': Paginate
        },

        /**
         * Data.
         * Mutable properties that can be accessed from within this component via "this" keyword
         * Note that v-if and v-for must never be on the same node together, as this overrides
         * the meaning of "this" in a way you don't expect.
         */
        data() {
            return {

                /* True, when data is currently loading; used to show skeleton
                 */
                loading: true,

                /* The full list of users who visited this article, obtained asynchronously
                 * This stack gets paginated elsewhere
                 */
                allUsers: [],

                /* The current page number
                 */
                page: 1
            }
        },

        /**
         * Mounted.
         * Basically, when vue has done all its inits and is ready to go
         * kind of like window.onload
         */
        mounted: function () {
            let self = this;

            /* Show skeleton
             */
            this.loading = true;

            /* Fetch payload asynchronously
             */
            let request = {
                url: ENDPOINTS.payloadFullPath(this.payload.article.id),
                method: 'get',
                responseType: 'json'
            }

            axios.request(request)
                    .then(function (response) {

                        /* Load into vue component
                         */
                        self.allUsers = response.data;
                    })
                    .catch(error => {
                        if (error.response) {
                            self.loading = false;
                        }
                    })
                    .then(function () {

                        /* Hide skeleton
                         */
                        self.loading = false;
                    });

            /* Set up listener for window history state, for pagination refresh
             * Don't pushState when window.onpopstate executes otherwise the Back
             * button will only work once.
             *
             * https://stackoverflow.com/questions/50647136/forward-button-not-working-after-history-pushstate
             */
            window.onpopstate = function (event) {
                if (!event.state) {
                    // Return to defaults
                    self.page = 1;
                } else if (event.state.page) {
                    self.page = event.state.page;
                }
            }
        },

        /**
         * Computed properties.
         * Access the results of a function as if they were a property of an object.
         */
        computed: {

            /**
             * The total number of pages available for the data set obtained
             */
            pageCount: function () {
                return Math.ceil(this.allUsers.length / this.payload.numPerPage);
            },


            /**
             * Calculate the paginated subset of all data
             */
            users: function() {
                if (!this.allUsers.length) {
                    return [];
                }

                const offset = (this.page-1) * this.payload.numPerPage;
                const total = offset + this.payload.numPerPage;

                return this.allUsers.slice(offset, total);
            }
        },

        /**
         * Methods.
         * Arbitrary javascript functions which operate on the args provided, just like any
         * other ordinary javascript that you might have written before.
         */
        methods: {

            /**
             * Update the pushState history in the browser to simulate navigation
             * with async requests
             *
             * @param page int - The page to change to
             */
            onPaginate: function (page) {
                const params = '?' + 'p=' + page;

                window.history.pushState({page: page}, '', params);
            },
        }
    }
</script>
<template>
    <div>
        <div class="row">
            <div class="small-12 island">
                <h2 class="subheader">All Visitors</h2>

                <template v-if="this.allUsers.length || this.loading">
                    <section>
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Last Visited</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-if="this.loading">
                                    <tr v-for="n in 3">
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                </template>
                                <template v-else>
                                    <tr v-for="user in this.users">
                                        <td>
                                            <a :href="'/search/user/' + user.id" :title="user.fullname">
                                                {{ user.fullname }}
                                            </a>
                                        </td>
                                        <td>{{ user.role }}</td>
                                        <td>
                                            <template v-if="user.occursAt">
                                                {{ user.occursAt.nice }} ({{user.occursAt.relative}})
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </section>
                    <template v-if="!this.loading">
                        <Paginate
                            v-model="page"
                            :page-count="pageCount"
                            :container-class="'pagination'"
                            :prev-text="'‹'"
                            :next-text="'›'"
                            :prev-link-class="'arrow'"
                            :next-link-class="'arrow'"
                            :disabled-class="'unavailable'"
                            :click-handler="onPaginate"
                        ></Paginate>
                    </template>
                </template>
                <template v-else>
                    <div class="empty-state" v-if="!this.loading">
                        <i class="icon-staff"></i>
                        <p>Nobody has visited yet.</p>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
